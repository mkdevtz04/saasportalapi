<?php

namespace App\Services;

use App\Models\RouterCommand;
use App\Models\TenantRouter;
use App\Support\RouterOs;
use App\Support\TenantUrls;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Builds everything a router downloads from the platform: the one-time setup script, the
 * branded hotspot login page, the small agent script that runs on the router every
 * minute, and the command files the agent imports.
 *
 * Every value that a tenant can influence goes through RouterOs::quote() or
 * RouterOs::comment(). Templates use {{PLACEHOLDER}} markers instead of PHP interpolation
 * because RouterOS uses the dollar sign for its own variables.
 */
class ProvisioningScript
{
    /**
     * The command a tenant pastes into the router terminal.
     */
    public function oneLiner(TenantRouter $router): string
    {
        $url = TenantUrls::base() . '/provision/' . $router->getOrGenerateProvisionToken();

        return '/tool fetch url=' . RouterOs::quote($url)
            . ' dst-path=trinetpay-bootstrap.rsc mode=https check-certificate=no;'
            . ' :delay 2s; /import file-name=trinetpay-bootstrap.rsc;'
            . ' /file remove trinetpay-bootstrap.rsc';
    }

    /**
     * Setup script for a router that connects out to the platform (RADIUS mode).
     *
     * Every step runs inside its own error handler, so one command that a particular
     * RouterOS version does not understand cannot stop the rest. The names of steps that
     * failed are reported back to the platform and shown to the tenant.
     */
    public function radiusSetup(TenantRouter $router): string
    {
        $host   = (string) config('radius.host');
        $secret = (string) config('radius.secret');

        if ($host === '' || $secret === '') {
            throw new RuntimeException('RADIUS_HOST and RADIUS_SECRET must be set before routers can be set up.');
        }

        $tenant = $router->tenant;
        $token  = $router->getOrGenerateProvisionToken();
        $base   = TenantUrls::base();

        $walled = $this->walledGarden($tenant);

        return strtr(<<<'RSC'
# =========================================================
# TrinetPay router setup
# Tenant:    {{TENANT}}
# Router:    {{ROUTER}}
# Generated: {{TIME}}
# =========================================================
:log info "TrinetPay: setup started"
:local failed ""

# 1. Router identity. It is sent to the platform as NAS-Identifier, which is how the
#    platform knows which ISP this router belongs to.
:do {
  /system identity set name={{NAS}}
} on-error={ :set failed ($failed . "identity,") }

# 2. RADIUS server. The router connects out to it, nothing needs to reach the router.
:do {
  /radius remove [find comment="TrinetPay"]
  /radius add address={{RADIUS_HOST}} secret={{RADIUS_SECRET}} service=hotspot authentication-port={{AUTH_PORT}} accounting-port={{ACCT_PORT}} timeout=3s comment="TrinetPay"
} on-error={ :set failed ($failed . "radius,") }

# 3. Hotspot: log customers in through RADIUS, let known devices back in by MAC address.
#    A router that arrives with nothing configured has one built for it first.
:do {
{{HOTSPOT}}
  /ip hotspot profile set [find] use-radius=yes radius-accounting=yes radius-interim-update=received login-by=mac,http-pap
} on-error={ :set failed ($failed . "hotspot,") }

{{WIRELESS}}

# 4. Walled garden: what customers may open before they have paid.
:do {
  /ip hotspot walled-garden remove [find comment="TrinetPay"]
  {{WALLED}}
} on-error={ :set failed ($failed . "walled-garden,") }

# 5. Branded login page for this ISP, and the page shown the moment a login works. That second
#    page is what remembers a customer's code on their own device, so a phone whose WiFi was
#    switched off and on is put back online by the login page instead of being sent looking for
#    the code again.
:do {
  /tool fetch url={{LOGIN_URL}} dst-path="hotspot/login.html" mode=https check-certificate=no
  /tool fetch url={{ALOGIN_URL}} dst-path="hotspot/alogin.html" mode=https check-certificate=no
} on-error={ :set failed ($failed . "login-page,") }

# 6. Agent: reports to the platform every minute and picks up commands.
#    The ftp policy is what lets the agent write the reply to a file. Without it every poll
#    fails with "cannot open file: permission denied" and the router never hears about a
#    customer who has paid or used a voucher.
#
#    The scheduler goes first and comes back last, so a router being set up a second time has
#    nothing running in the moment between the old script being removed and the new one arriving.
:do {
  /system scheduler remove [find name="trinetpay-agent"]
  /system script remove [find name="trinetpay-agent"]
  /system script add name="trinetpay-agent" policy=ftp,read,write,policy,test,reboot source={{AGENT_SOURCE}}
  /system scheduler add name="trinetpay-agent" interval={{INTERVAL}} start-time=startup policy=ftp,read,write,policy,test,reboot comment="TrinetPay" on-event="/system script run trinetpay-agent"
} on-error={ :set failed ($failed . "agent,") }

# 7. Tell the platform we are done, and which steps did not work.
:do {
  /tool fetch url=({{COMPLETE_URL}} . $failed) mode=https keep-result=no check-certificate=no
} on-error={ :log error "TrinetPay: could not report to the platform" }
:log info "TrinetPay: setup finished"
RSC, [
            '{{TENANT}}'       => RouterOs::comment($tenant->name),
            '{{ROUTER}}'       => RouterOs::comment($router->name),
            '{{TIME}}'         => now()->toDateTimeString(),
            '{{NAS}}'          => RouterOs::quote((string) $router->nas_identifier),
            '{{RADIUS_HOST}}'  => RouterOs::quote($host),
            '{{RADIUS_SECRET}}' => RouterOs::quote($secret),
            '{{AUTH_PORT}}'    => (string) (int) config('radius.auth_port'),
            '{{ACCT_PORT}}'    => (string) (int) config('radius.acct_port'),
            '{{HOTSPOT}}'      => $this->hotspotIfMissing(),
            '{{WIRELESS}}'     => $this->wirelessOpen($tenant),
            '{{WALLED}}'       => $walled,
            '{{LOGIN_URL}}'    => RouterOs::quote($base . '/provision/' . $token . '/login.html'),
            '{{ALOGIN_URL}}'   => RouterOs::quote($base . '/provision/' . $token . '/alogin.html'),
            '{{AGENT_SOURCE}}' => RouterOs::quote($this->agentSource($router)),
            '{{INTERVAL}}'     => RouterOs::bareName((string) config('radius.agent_interval'), '1m'),
            '{{COMPLETE_URL}}' => RouterOs::quote($base . '/provision/' . $token . '/complete?failed='),
        ]);
    }

    /**
     * Setup script for agent mode: no RADIUS, no API and nothing that has to reach the router.
     * The router calls the platform every few seconds and creates customers' hotspot users itself.
     */
    public function agentSetup(TenantRouter $router): string
    {
        $tenant = $router->tenant;
        $token  = $router->getOrGenerateProvisionToken();
        $base   = TenantUrls::base();

        return strtr(<<<'RSC'
# =========================================================
# TrinetPay router setup
# Tenant:    {{TENANT}}
# Router:    {{ROUTER}}
# Generated: {{TIME}}
# =========================================================
:log info "TrinetPay: setup started"
:local failed ""

# 1. Hotspot: customers log in with the code they get after paying. Cookies keep a device logged in.
#    A router that arrives with nothing configured has one built for it first.
:do {
{{HOTSPOT}}
  /ip hotspot profile set [find] use-radius=no login-by=cookie,http-pap
} on-error={ :set failed ($failed . "hotspot,") }

# 1b. Remember a device by its MAC address, so a phone whose WiFi is switched off and on is put
#     straight back online instead of being sent to the portal to find its code again. Its own
#     step, and only a note in the log if it fails, because the oldest RouterOS versions have no
#     mac-cookie and the rest of the setup must still go through on them. How long that memory
#     lasts is left at whatever the router already uses, three days as it ships: naming the field
#     would be one more guess at a property, and a wrong one is not caught here but stops the
#     whole import before the agent is ever installed.
:do {
  /ip hotspot profile set [find] login-by=mac-cookie,cookie,http-pap
} on-error={ :log warning "TrinetPay: this RouterOS has no mac-cookie, returning devices will sign in again" }

{{WIRELESS}}

# 2. Walled garden: what customers may open before they have paid.
:do {
  /ip hotspot walled-garden remove [find comment="TrinetPay"]
  {{WALLED}}
} on-error={ :set failed ($failed . "walled-garden,") }

# 3. Branded login page for this ISP, and the page shown the moment a login works. That second
#    page is what remembers a customer's code on their own device, so a phone whose WiFi was
#    switched off and on is put back online by the login page instead of being sent looking for
#    the code again.
:do {
  /tool fetch url={{LOGIN_URL}} dst-path="hotspot/login.html" mode=https check-certificate=no
  /tool fetch url={{ALOGIN_URL}} dst-path="hotspot/alogin.html" mode=https check-certificate=no
} on-error={ :set failed ($failed . "login-page,") }

# 4. Agent: calls the platform every few seconds and creates customers' hotspot users.
#    The ftp policy is what lets the agent write the reply to a file. Without it every poll
#    fails with "cannot open file: permission denied" and the router never hears about a
#    customer who has paid or used a voucher.
#
#    The scheduler goes first and comes back last. A router being set up a second time still has
#    the old scheduler running, and on a ten second interval it will fire in the moment between
#    the old script being removed and the new one being added — which is the "no such item
#    (/system/script/run)" and the string of "script error: interrupted" lines in the log. Taking
#    the scheduler away before touching the script leaves nothing running to trip over it.
:do {
  /system scheduler remove [find name="trinetpay-agent"]
  /system script remove [find name="trinetpay-agent"]
  /system script add name="trinetpay-agent" policy=ftp,read,write,policy,test,reboot source={{AGENT_SOURCE}}
  /system scheduler add name="trinetpay-agent" interval={{INTERVAL}} start-time=startup policy=ftp,read,write,policy,test,reboot comment="TrinetPay" on-event="/system script run trinetpay-agent"
} on-error={ :set failed ($failed . "agent,") }

# 5. Tell the platform we are done, and which steps did not work.
:do {
  /tool fetch url=({{COMPLETE_URL}} . $failed) mode=https keep-result=no check-certificate=no
} on-error={ :log error "TrinetPay: could not report to the platform" }
:log info "TrinetPay: setup finished"
RSC, [
            '{{TENANT}}'       => RouterOs::comment($tenant->name),
            '{{ROUTER}}'       => RouterOs::comment($router->name),
            '{{TIME}}'         => now()->toDateTimeString(),
            '{{HOTSPOT}}'      => $this->hotspotIfMissing(),
            '{{WIRELESS}}'     => $this->wirelessOpen($tenant),
            '{{WALLED}}'       => $this->walledGarden($tenant),
            '{{LOGIN_URL}}'    => RouterOs::quote($base . '/provision/' . $token . '/login.html'),
            '{{ALOGIN_URL}}'   => RouterOs::quote($base . '/provision/' . $token . '/alogin.html'),
            '{{AGENT_SOURCE}}' => RouterOs::quote($this->agentSource($router)),
            '{{INTERVAL}}'     => RouterOs::bareName((string) config('router.agent_interval'), '10s'),
            '{{COMPLETE_URL}}' => RouterOs::quote($base . '/provision/' . $token . '/complete?failed='),
        ]);
    }

    /**
     * Builds a hotspot on a router that has none, so an ISP handed a customer's brand new router
     * can set it up with the one command and nothing else.
     *
     * It only ever runs when there is no hotspot at all. A router already serving customers is
     * left exactly as it is, because its owner chose that layout and a second hotspot on top of
     * it would take their customers offline.
     *
     * The uplink is worked out first and then avoided everywhere: a hotspot on the internet side
     * would put the router's own connection behind a login page, and a WAN port swept into the
     * customer bridge would cut the router off altogether. An interface is treated as uplink when
     * it asks for an address by DHCP, when it already carries one, or when the default route
     * leaves by it. Where none of that can be worked out, the step gives up rather than guess,
     * and the dashboard says the hotspot could not be set up.
     *
     * Every part is on its own error handler. Routers differ — no wireless, a bridge already in
     * use, a DHCP server somewhere else — and a part that does not apply must not stop the rest.
     */
    private function hotspotIfMissing(): string
    {
        return <<<'RSC'
  :if ([:len [/ip hotspot find]] = 0) do={
    :local wan ""
    :local lan ""
    :local addr ""
    :local gw ""
    :local hspool "none"

    # The side facing the internet, so that everything below can stay away from it.
    :foreach c in=[/ip dhcp-client find] do={
      :if ($wan = "") do={ :do { :set wan [/ip dhcp-client get $c interface] } on-error={} }
    }
    :if ($wan = "") do={
      :foreach r in=[/ip route find dst-address="0.0.0.0/0"] do={
        :if ($wan = "") do={ :do { :set wan [/ip route get $r interface] } on-error={} }
      }
    }

    # The side facing customers: the bridge the router already has, which on a factory-fresh
    # MikroTik is every port but the uplink, already addressed and handing out DHCP.
    :foreach b in=[/interface bridge find] do={
      :local n [/interface bridge get $b name]
      :if (($lan = "") && ($n != $wan)) do={ :set lan $n }
    }

    # No bridge to use, so one is made. A port is left out when anything suggests it is the
    # uplink, because sweeping the uplink in here would take the router off the internet.
    :if ($lan = "") do={
      :do {
        /interface bridge add name="trinetpay" comment="TrinetPay"
        :set lan "trinetpay"
        :foreach e in=[/interface ethernet find] do={
          :local n [/interface ethernet get $e name]
          :local skip 0
          :if ($n = $wan) do={ :set skip 1 }
          :if ([:len [/ip dhcp-client find interface=$n]] > 0) do={ :set skip 1 }
          :if ([:len [/ip address find interface=$n]] > 0) do={ :set skip 1 }
          :if ($skip = 0) do={ :do { /interface bridge port add bridge="trinetpay" interface=$n } on-error={} }
        }

        # The radios belong on the same bridge, or customers on Wi-Fi never reach the hotspot at
        # all. They are picked out by name from the plain interface list, because the two wireless
        # stacks live under different menus and only one of them exists on any given router.
        :foreach w in=[/interface find] do={
          :local n [/interface get $w name]
          :if ([:len $n] > 3) do={
            :if (([:pick $n 0 4] = "wlan") || ([:pick $n 0 4] = "wifi")) do={
              :do { /interface bridge port add bridge="trinetpay" interface=$n } on-error={}
            }
          }
        }
      } on-error={ :set lan "" }
    }

    :if ($lan = "") do={ :error "found no interface to put a hotspot on" }

    # An address for customers to use as their gateway, and somewhere for their addresses to come
    # from. Both are left alone when the router already has them, which is the usual case.
    :if ([:len [/ip address find interface=$lan]] = 0) do={
      /ip address add address=10.88.0.1/24 interface=$lan comment="TrinetPay"
      :do {
        /ip pool add name="trinetpay" ranges=10.88.0.10-10.88.0.254
        /ip dhcp-server add name="trinetpay" interface=$lan address-pool="trinetpay" lease-time=1h disabled=no comment="TrinetPay"
        /ip dhcp-server network add address=10.88.0.0/24 gateway=10.88.0.1 dns-server=10.88.0.1 comment="TrinetPay"
        :set hspool "trinetpay"
      } on-error={ }
    }

    :foreach a in=[/ip address find interface=$lan] do={
      :if ($addr = "") do={ :set addr [/ip address get $a address] }
    }
    :set gw [:pick $addr 0 [:find $addr "/"]]

    # Whatever the router already hands out to customers on this side. Left as none when there is
    # no DHCP server here: their addresses then come from wherever they come from today, and the
    # hotspot works the same.
    :foreach d in=[/ip dhcp-server find interface=$lan] do={
      :if ($hspool = "none") do={ :do { :set hspool [/ip dhcp-server get $d address-pool] } on-error={} }
    }

    # Customers cannot reach the internet without these two, and a factory-fresh router has both.
    :if ([:len [/ip firewall nat find action="masquerade"]] = 0) do={
      :if ($wan != "") do={ :do { /ip firewall nat add chain=srcnat action=masquerade out-interface=$wan comment="TrinetPay" } on-error={} }
    }
    :do { /ip dns set allow-remote-requests=yes } on-error={ }

    # The profile and the hotspot itself.
    #
    # Not one property here is optional guesswork. RouterOS checks property names while it reads
    # the file, before it runs any of it, so an invented one is a parse error that stops the whole
    # import dead — every step below it, agent included, never happens. on-error cannot catch that:
    # it only catches errors from running, and nothing ever ran. A hotspot profile has no comment
    # field on RouterOS 7, and asking for one cost three failed setups before that was clear.
    #
    # So: only fields that are certain, and nothing decorative. A name left over from an earlier
    # half-finished run is removed first, so pasting the command again is never blocked by its own
    # last attempt.
    /ip hotspot profile remove [find name="trinetpay"]
    /ip hotspot profile add name="trinetpay"
    /ip hotspot profile set [find name="trinetpay"] login-by=cookie,http-pap

    /ip hotspot remove [find name="trinetpay"]
    /ip hotspot add name="trinetpay" interface=$lan
    /ip hotspot set [find name="trinetpay"] profile="trinetpay"
    :if ($hspool != "none") do={ /ip hotspot set [find name="trinetpay"] address-pool=$hspool }

    :log info ("TrinetPay: hotspot created on " . $lan . " at " . $gw)
  }
RSC;
    }

    /**
     * The Wi-Fi name customers look for when they want to buy: the ISP's own name.
     *
     * Cut to the 32 bytes 802.11 allows, and down to characters that survive being pasted into a
     * command the router assembles for itself — a quote or a backslash in the middle of one would
     * end the string early, and the radio would keep whatever name it shipped with.
     */
    private function ssid($tenant): string
    {
        $name = preg_replace('/[^A-Za-z0-9 ._-]+/', '', (string) $tenant->name);
        $name = trim((string) preg_replace('/\s+/', ' ', (string) $name));

        return mb_strcut($name !== '' ? $name : 'WiFi', 0, 32);
    }

    /**
     * Names the Wi-Fi after the ISP and takes the password off it.
     *
     * A hotspot router's radio has to be open. The hotspot is the lock: a customer is meant to
     * associate freely, be caught by the portal and pay there. A radio still asking for the key
     * printed on the box is a wall in front of the payment page — nobody reaches the portal, and
     * the ISP sells nothing.
     *
     * RouterOS 7 has two wireless stacks and a router carries one or the other, so whichever one
     * this router does not have is a menu that is not there. Naming a missing menu is a parse
     * error, and a parse error anywhere stops the whole file before the agent is ever installed.
     * Handing each to :parse turns that into an ordinary runtime error, which the handler around
     * it can swallow — so the stack this router does have is configured, and the other passes
     * quietly. Each property goes on its own line for the same reason: the name matters most, and
     * a mode or a cipher this build spells differently must not cost the ISP their SSID.
     */
    private function wirelessOpen($tenant): string
    {
        return strtr(<<<'RSC'
# Wi-Fi: named after the ISP and left open, because the hotspot is the lock and a customer who
# cannot associate never sees the portal at all.
:local ssid {{SSID}}
:local radio ""
:do { [[:parse "/interface wireless security-profiles set [find] mode=none"]] } on-error={ }
:do { [[:parse "/interface wireless set [find] security-profile=default"]] } on-error={ }
:do { [[:parse ("/interface wireless set [find] ssid=\"" . $ssid . "\" disabled=no")]]; :set radio "wireless" } on-error={ }
:do { [[:parse "/interface wireless set [find] mode=ap-bridge"]] } on-error={ }
:do { [[:parse ("/interface wifi set [find] configuration.ssid=\"" . $ssid . "\"")]]; :set radio "wifi" } on-error={ }
:do { [[:parse "/interface wifi set [find] security.authentication-types=\"\""]] } on-error={ }
:do { [[:parse "/interface wifi set [find] configuration.mode=ap disabled=no"]] } on-error={ }

# Which stack answered, so an ISP looking at the log can tell "the name did not take" apart from
# "this router has neither menu" without having to guess at it.
:if ($radio = "") do={
  :log warning ("TrinetPay: could not name the wifi, set it to " . $ssid . " by hand and turn its password off")
} else={
  :log info ("TrinetPay: wifi named " . $ssid . " and left open, via " . $radio)
}
RSC, ['{{SSID}}' => RouterOs::quote($this->ssid($tenant))]);
    }

    /** One walled-garden line per host a customer must reach before paying. */
    private function walledGarden($tenant): string
    {
        return collect([
            TenantUrls::portalHost($tenant),
            TenantUrls::baseHost(),
            'cdnjs.cloudflare.com',
            (string) parse_url((string) config('services.palmpesa.base_url'), PHP_URL_HOST),
        ])->filter()->unique()->map(
            fn ($dst) => '/ip hotspot walled-garden add dst-host=' . RouterOs::quote($dst) . ' comment="TrinetPay"'
        )->implode("\n  ");
    }

    /**
     * The script stored on the router that runs every few seconds. It reports the router's state and
     * reads the commands the platform has waiting. It never runs downloaded text as a script: each
     * line is matched against a fixed shape and every value is checked again on the router.
     */
    public function agentSource(TenantRouter $router): string
    {
        $poll = TenantUrls::base() . '/api/agent/' . $router->getOrGenerateAgentToken() . '/poll';

        // Only routers logged in through RADIUS need to keep their platform name, so only they report it.
        $identity = $router->isRadius()
            ? ':local idok 0;' . "\n" . ':do { :if ([/system identity get name] = ' . RouterOs::quote((string) $router->nas_identifier) . ') do={ :set idok 1 } } on-error={ };'
            : ':local idok "";';
        $idParam  = $router->isRadius() ? ' . "&m=" . $idok' : '';

        // No lock against overlapping runs on purpose. One run takes about three seconds against a
        // ten second interval, and the platform hands each command out only once, so an overlap is
        // harmless. A lock would stay stuck if a run were ever interrupted, and the router would
        // then stop reporting for good.
        return strtr(<<<'RSC'
:do {
:local base {{POLL_URL}};
{{IDENTITY}}
:local ver [/system resource get version];
:local cut [:find $ver " "];
:if ([:typeof $cut] = "nil") do={ :set cut [:len $ver] };
:set ver [:pick $ver 0 $cut];
:local users 0;
:do { :set users [:len [/ip hotspot active find]] } on-error={ };
:local up [/system resource get uptime];
/tool fetch url=($base . "?v=" . $ver . "&n=" . $users . "&u=" . $up{{IDPARAM}}) mode=https dst-path="trinetpay-cmd.txt" check-certificate=no;
:delay 2s;
:if ([:len [/file find name="trinetpay-cmd.txt"]] > 0) do={
  :local content [/file get [/file find name="trinetpay-cmd.txt"] contents];
  /file remove [/file find name="trinetpay-cmd.txt"];
  :local pos 0;
  :while ($pos < [:len $content]) do={
    :local eol [:find $content "\n" $pos];
    :if ([:typeof $eol] = "nil") do={ :set eol [:len $content] };
    :local line [:pick $content $pos $eol];
    :set pos ($eol + 1);
    :if ($line = "reboot") do={ /system reboot };
    :if ($line = "kick_all") do={ /ip hotspot active remove [find]; /ip hotspot cookie remove [find] };
    :if ([:len $line] > 5) do={
      :if ([:pick $line 0 5] = "kick ") do={
        :local u [:pick $line 5 [:len $line]];
        :if ($u ~ "^[A-Za-z0-9:_.-]+\$") do={
          /ip hotspot active remove [find user=$u];
          /ip hotspot cookie remove [find user=$u];
        }
      }
    }
    :if ([:len $line] > 11) do={
      :if ([:pick $line 0 11] = "removeuser ") do={
        :local u [:pick $line 11 [:len $line]];
        :if ($u ~ "^[A-Za-z0-9:_.-]+\$") do={
          /ip hotspot user remove [find name=$u];
          /ip hotspot active remove [find user=$u];
          /ip hotspot cookie remove [find user=$u];
        }
      }
    }
    :if ([:len $line] > 8) do={
      :if ([:pick $line 0 8] = "adduser ") do={
        :local rest ([:pick $line 8 [:len $line]] . " ");
        :local parts [:toarray ""];
        :local start 0;
        :for i from=0 to=([:len $rest] - 1) do={
          :if ([:pick $rest $i ($i + 1)] = " ") do={
            :set parts ($parts, [:pick $rest $start $i]);
            :set start ($i + 1);
          }
        }
        :if ([:len $parts] = 5) do={
          :local user [:pick $parts 0];
          :local prof [:pick $parts 1];
          :local rate [:pick $parts 2];
          :local secs [:pick $parts 3];
          :local bytes [:pick $parts 4];
          :if (($user ~ "^[A-Za-z0-9:_.-]+\$") && ($prof ~ "^[A-Za-z0-9_-]+\$") && ($rate ~ "^[0-9]+[MK]/[0-9]+[MK]\$") && ($secs ~ "^[0-9]+\$") && ($bytes ~ "^[0-9]+\$")) do={
            :if ([:len [/ip hotspot user profile find name=$prof]] = 0) do={
              /ip hotspot user profile add name=$prof rate-limit=$rate shared-users=1;
            } else={
              /ip hotspot user profile set [find name=$prof] rate-limit=$rate;
            }
            :if ([:len [/ip hotspot user find name=$user]] > 0) do={ /ip hotspot user remove [find name=$user] };
            /ip hotspot user add name=$user password=$user profile=$prof limit-uptime=[:totime ($secs . "s")] limit-bytes-total=$bytes comment="TrinetPay";
          }
        }
      }
    }
  }
}
} on-error={ :log warning "TrinetPay agent: could not reach the platform" };
RSC, [
            '{{POLL_URL}}' => RouterOs::quote($poll),
            '{{IDENTITY}}' => $identity,
            '{{IDPARAM}}'  => $idParam,
        ]);
    }

    /**
     * The reply to an agent poll: plain lines the router reads, never a script it runs.
     *
     * The grammar is five shapes and nothing else:
     *   reboot
     *   kick_all
     *   kick <login>
     *   removeuser <login>
     *   adduser <login> <profile> <up>M/<down>M <seconds> <bytes>
     *
     * The router acts only on those exact shapes and checks every value again on its side. So a
     * reply that was tampered with in transit can restart the router, disconnect customers or add
     * or remove a hotspot user, at worst. It cannot make the router run a command of the
     * attacker's choosing.
     *
     * @param Collection<int,RouterCommand> $commands
     */
    public function agentCommands(Collection $commands): string
    {
        $lines = ['# TrinetPay commands, generated ' . now()->toDateTimeString()];

        foreach ($commands as $command) {
            $payload = (array) ($command->payload ?? []);

            $lines[] = match ($command->type) {
                RouterCommand::KICK_USER   => self::loginLine('kick', (string) ($payload['username'] ?? '')),
                RouterCommand::REMOVE_USER => self::loginLine('removeuser', (string) ($payload['username'] ?? '')),
                RouterCommand::ADD_USER    => self::addUserLine($payload),
                RouterCommand::KICK_ALL    => 'kick_all',
                RouterCommand::REBOOT      => 'reboot',
                default                    => '# unknown command skipped',
            };
        }

        return implode("\n", $lines) . "\n";
    }

    /** A login name that is not made of the plain characters a login can contain is skipped. */
    private static function loginLine(string $verb, string $username): string
    {
        return preg_match('/^[A-Za-z0-9:_.\-]{1,64}$/', $username)
            ? $verb . ' ' . $username
            : '# ' . $verb . ' skipped, the login name was not valid';
    }

    /** @param array<string,mixed> $payload */
    private static function addUserLine(array $payload): string
    {
        $user    = (string) ($payload['username'] ?? '');
        $profile = (string) ($payload['profile'] ?? '');
        $rate    = (string) ($payload['rate'] ?? '');
        $seconds = (string) ($payload['seconds'] ?? '');
        $bytes   = (string) ($payload['bytes'] ?? '');

        $valid = preg_match('/^[A-Za-z0-9:_.\-]{1,64}$/', $user)
            && preg_match('/^[A-Za-z0-9_\-]{1,40}$/', $profile)
            && preg_match('/^\d{1,5}M\/\d{1,5}M$/', $rate)
            && preg_match('/^\d{1,9}$/', $seconds)
            && preg_match('/^\d{1,12}$/', $bytes);

        return $valid
            ? "adduser {$user} {$profile} {$rate} {$seconds} {$bytes}"
            : '# adduser skipped, a value was not valid';
    }

    /**
     * Setup script for the older way of working, where the platform logs in to the router.
     * Kept so existing routers keep working until their owners switch to the new setup.
     */
    public function apiSetup(TenantRouter $router): string
    {
        $tenant = $router->tenant;
        $token  = $router->getOrGenerateProvisionToken();
        $base   = TenantUrls::base();

        $portalHost = TenantUrls::portalHost($tenant);
        $mainHost   = TenantUrls::baseHost();

        return strtr(<<<'RSC'
# =========================================================
# TrinetPay router setup (API mode)
# Tenant:    {{TENANT}}
# Router:    {{ROUTER}} ({{NAS_COMMENT}})
# Generated: {{TIME}}
# =========================================================
:log info "TrinetPay: setup started"

/system backup save name="trinetpay-backup"

/ip service enable api
/ip service set api port={{API_PORT}}

:if ([:len [/user find name={{USER}}]] = 0) do={
  /user add name={{USER}} password={{PASS}} group=full comment="TrinetPay API user"
} else={
  /user set [find name={{USER}}] password={{PASS}} group=full
}

/ip hotspot walled-garden
:if ([:len [find dst-host={{PORTAL_HOST}}]] = 0) do={ add dst-host={{PORTAL_HOST}} comment="TrinetPay portal" }
:if ([:len [find dst-host={{MAIN_HOST}}]] = 0) do={ add dst-host={{MAIN_HOST}} comment="TrinetPay site" }
:if ([:len [find dst-host={{PAY_HOST}}]] = 0) do={ add dst-host={{PAY_HOST}} comment="PalmPesa" }

/ip hotspot profile set [find] login-by=http-pap

/tool fetch url={{COMPLETE_URL}} keep-result=no check-certificate=no
:log info "TrinetPay: setup finished"
RSC, [
            '{{TENANT}}'      => RouterOs::comment($tenant->name),
            '{{ROUTER}}'      => RouterOs::comment($router->name),
            '{{NAS_COMMENT}}' => RouterOs::comment((string) $router->nas_identifier),
            '{{TIME}}'        => now()->toDateTimeString(),
            '{{API_PORT}}'    => (string) (int) ($router->port ?: 8728),
            '{{USER}}'        => RouterOs::quote((string) $router->username),
            '{{PASS}}'        => RouterOs::quote((string) $router->password),
            '{{PORTAL_HOST}}' => RouterOs::quote($portalHost),
            '{{MAIN_HOST}}'   => RouterOs::quote($mainHost),
            '{{PAY_HOST}}'    => RouterOs::quote((string) parse_url((string) config('services.palmpesa.base_url'), PHP_URL_HOST)),
            '{{COMPLETE_URL}}' => RouterOs::quote($base . '/provision/' . $token . '/complete'),
        ]);
    }

    /**
     * The hotspot login page the router serves to customers. Branded for the ISP, it sends
     * people to the portal to pay or redeem a voucher, puts a device that has been here before
     * straight back online, and still lets a customer log in with a code by hand.
     */
    public function loginPage(TenantRouter $router): string
    {
        $tenant = $router->tenant;
        $color  = (string) ($tenant->settings?->brand_color ?? '');
        $color  = preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#2561e8';

        $portal = TenantUrls::portal($tenant);
        $join   = str_contains($portal, '?') ? '&amp;' : '?';
        $buyUrl = htmlspecialchars($portal, ENT_QUOTES) . $join
            . 'mac=$(mac-esc)&amp;ip=$(ip)&amp;link-login-only=$(link-login-only-esc)&amp;link-orig=$(link-orig-esc)'
            . '&amp;nas=' . rawurlencode((string) $router->nas_identifier);

        return strtr(<<<'HTML'
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="pragma" content="no-cache">
<meta http-equiv="expires" content="-1">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{NAME}} - WiFi</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100%;background:#eef3f7;font-family:Arial,Helvetica,sans-serif;color:#040a17}
.page{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{width:100%;max-width:420px;background:#fff;border:1px solid #d8dee8;box-shadow:0 16px 48px rgba(20,32,51,.14)}
.header{padding:26px 28px 20px;border-bottom:3px solid {{COLOR}}}
.brand{font-size:22px;font-weight:900;line-height:1.1}
.sub{font-size:11px;font-weight:800;color:#526173;letter-spacing:.1em;text-transform:uppercase;margin-top:6px}
.body{padding:28px}
.error{padding:11px 13px;margin-bottom:18px;background:#fff1f1;border-left:4px solid #c62828;color:#a81717;font-size:13px;font-weight:700}
.again{text-align:center;padding:10px 0}
.again .spin{width:38px;height:38px;margin:0 auto 16px;border:3px solid #e8e8e8;border-top-color:{{COLOR}};border-radius:50%;animation:spin .8s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.again .lead{font-size:16px;font-weight:900}
.again .note{font-size:12px;color:#667085;margin-top:6px}
.buy{display:block;text-align:center;padding:16px;background:{{COLOR}};color:#fff;font-size:14px;font-weight:900;text-decoration:none;letter-spacing:.04em;text-transform:uppercase}
.divider{margin:24px 0 18px;border:0;border-top:1px solid #e5e9f0}
label{display:block;font-size:11px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;color:#344054;margin-bottom:7px}
input[name="code"]{width:100%;height:48px;padding:0 14px;font-size:16px;border:1.5px solid #b8c2d1;outline:none;background:#fbfdff;color:#040a17}
.btn{display:block;width:100%;height:48px;margin-top:12px;background:#040a17;color:#fff;border:0;font-size:13px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;cursor:pointer}
.footer{padding:14px 28px;background:#f7f9fb;border-top:1px solid #e5e9f0;font-size:11px;color:#667085;text-align:center}
@media (max-width:420px){
  .page{padding:0}
  .card{border-left:0;border-right:0}
  .header{padding:20px 16px}
  .body{padding:20px 16px}
  .footer{padding:12px 16px}
  .brand{font-size:19px}
}
</style>
</head>
<body>
<main class="page">
  <div class="card">
    <div class="header">
      <div class="brand">{{NAME}}</div>
      <div class="sub">WiFi Hotspot</div>
    </div>
    <div class="body">
      <p class="error" style="display:none" id="err">$(error)</p>

      <!-- Shown instead of the choices below while a remembered code is being sent to this router. -->
      <div class="again" id="again" style="display:none">
        <div class="spin"></div>
        <div class="lead">Reconnecting you&hellip;</div>
        <div class="note">Tunakuunganisha tena&hellip;</div>
      </div>

      <div id="choices">
        <a class="buy" href="{{BUY_URL}}">Buy WiFi or use a voucher &nbsp;/&nbsp; Nunua WiFi au tumia vocha</a>

        <hr class="divider">

        <!--
          The code goes to the portal, not to this router. A voucher the ISP generated in the
          dashboard is only a printed code until someone uses it: the router is told about it at
          that moment, not before. Sending it here would fail with "invalid username or password".
          The portal redeems it and then logs the customer in. A GET form, so the browser encodes
          the values for us and the raw hotspot variables are the right ones to use.
        -->
        <form method="get" action="{{PORTAL_URL}}">
          <input type="hidden" name="mac" value="$(mac)">
          <input type="hidden" name="ip" value="$(ip)">
          <input type="hidden" name="link-login-only" value="$(link-login-only)">
          <input type="hidden" name="link-orig" value="$(link-orig)">
          <input type="hidden" name="nas" value="{{NAS}}">
          <label for="code">Have a code or voucher? &nbsp;/&nbsp; Una namba au vocha?</label>
          <input id="code" name="code" type="text" autocomplete="off" required>
          <button class="btn" type="submit">Connect &nbsp;/&nbsp; Unganisha</button>
        </form>
      </div>
    </div>
    <div class="footer">{{NAME}}</div>
  </div>
</main>

<!-- A code this device has used here before, put back to the router's own login. -->
<form id="reconnect" method="post" action="$(link-login-only)">
  <input type="hidden" name="username" value="">
  <input type="hidden" name="password" value="">
  <input type="hidden" name="dst" value="$(link-orig)">
</form>

<script>
(function () {
  // Reconnecting a returning device happens here, on the router's own login page, and nowhere
  // else. This page is the only one that always knows this router's login address: the portal
  // only ever learns it from the link, and a customer coming back often arrives without one — a
  // captive-portal window that dropped the query string, a bookmark, or history. A code with
  // nowhere to send it is no use to them.
  //
  // The code is kept in this browser against this router's address, by the page the router shows
  // when a login works, and is handed back the moment the router asks for a login again.
  var KEY   = 'trinetpay-code';
  var TRIED = 'trinetpay-tried';
  var err   = document.getElementById('err');

  if (err && err.textContent.trim() !== '') {
    // The router refused a login. For a remembered code that means it has run out or was removed,
    // so it is dropped: the customer sees the page rather than a reconnect that cannot work.
    err.style.display = 'block';
    try { localStorage.removeItem(KEY); } catch (e) {}
    return;
  }

  var code = null, tried = 0;

  try {
    code  = localStorage.getItem(KEY);
    tried = parseInt(localStorage.getItem(TRIED) || '0', 10);
  } catch (e) {
    return;   // private browsing keeps nothing, so there is nothing to reconnect with
  }

  // Only a plain code, and never twice within half a minute. A router that refuses a login
  // without saying why would otherwise send this page round in a circle.
  if (!code || !/^[A-Za-z0-9]{4,32}$/.test(code) || (Date.now() - tried) < 30000) {
    return;
  }

  try { localStorage.setItem(TRIED, String(Date.now())); } catch (e) {}

  document.getElementById('choices').style.display = 'none';
  document.getElementById('again').style.display   = 'block';

  var form = document.getElementById('reconnect');
  form.username.value = code;
  form.password.value = code;
  form.submit();
})();
</script>
</body>
</html>
HTML, [
            '{{NAME}}'       => e($tenant->name),
            '{{COLOR}}'      => $color,
            '{{BUY_URL}}'    => $buyUrl,
            '{{PORTAL_URL}}' => htmlspecialchars($portal, ENT_QUOTES),
            '{{NAS}}'        => e((string) $router->nas_identifier),
        ]);
    }

    /**
     * The page the router shows the moment a login works, on the way to wherever the customer was
     * going.
     *
     * This is the one place that sees both the code that just worked and the router it worked on,
     * so this is where the code is kept on the customer's device. That is what lets the login page
     * put them straight back online later, without a trip to the portal to look up a code the
     * platform cannot always match to a router.
     */
    public function afterLoginPage(TenantRouter $router): string
    {
        $tenant = $router->tenant;
        $color  = (string) ($tenant->settings?->brand_color ?? '');
        $color  = preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#2561e8';

        return strtr(<<<'HTML'
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="pragma" content="no-cache">
<meta http-equiv="expires" content="-1">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{NAME}} - Connected</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100%;background:#eef3f7;font-family:Arial,Helvetica,sans-serif;color:#040a17}
.page{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{width:100%;max-width:380px;background:#fff;border:1px solid #d8dee8;box-shadow:0 16px 48px rgba(20,32,51,.14);text-align:center;padding:34px 26px}
.brand{font-size:20px;font-weight:900}
.lead{margin-top:18px;font-size:17px;font-weight:900;color:#15803d}
.note{margin-top:8px;font-size:13px;color:#667085;line-height:1.6}
.go{display:block;margin-top:22px;padding:14px;background:{{COLOR}};color:#fff;font-size:13px;font-weight:900;letter-spacing:.06em;text-transform:uppercase;text-decoration:none}
</style>
</head>
<body>
<main class="page">
  <div class="card">
    <div class="brand">{{NAME}}</div>
    <div class="lead">You are online</div>
    <div class="note">Umeunganishwa. This device will reconnect on its own next time.<br>Kifaa hiki kitaunganishwa chenyewe mara ijayo.</div>
    <a class="go" id="go" href="$(link-redirect)">Continue &nbsp;/&nbsp; Endelea</a>
  </div>
</main>

<span id="who" data-user="$(username)" data-go="$(link-redirect)" data-status="$(link-status)" style="display:none"></span>

<script>
(function () {
  var who = document.getElementById('who');

  // A device let back in by its MAC address logs in as that address, which is not something the
  // login page can post back as a code, so only plain codes are kept.
  var user = who.getAttribute('data-user') || '';

  if (/^[A-Za-z0-9]{4,32}$/.test(user)) {
    try {
      localStorage.setItem('trinetpay-code', user);
      localStorage.removeItem('trinetpay-tried');
    } catch (e) { /* private browsing: this device will sign in by hand next time */ }
  }

  // On to where they were going. Some routers leave that empty, in which case their own status
  // page is the honest landing: it is local, always there, and shows what they have left.
  var go = who.getAttribute('data-go') || '';

  if (go.indexOf('http') !== 0) {
    go = who.getAttribute('data-status') || '';
  }

  if (go.indexOf('http') === 0) {
    document.getElementById('go').href = go;
    setTimeout(function () { location.href = go; }, 1200);
  }
})();
</script>
</body>
</html>
HTML, [
            '{{NAME}}'  => e($tenant->name),
            '{{COLOR}}' => $color,
        ]);
    }
}
