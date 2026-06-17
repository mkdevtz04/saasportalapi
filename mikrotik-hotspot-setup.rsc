# Adjust interface/profile names before pasting into RouterOS.
# Upload mikrotik-login.html to the router Hotspot files as login.html.

/ip dns set allow-remote-requests=yes

# Clients must use the MikroTik router as gateway and DNS.
# Change 192.168.88.1 if your hotspot gateway is different.
/ip dhcp-server network set [find] gateway=192.168.88.1 dns-server=192.168.88.1

# Let unpaid clients reach the external portal and payment provider.
/ip hotspot walled-garden add dst-host=trinetpay.online comment="TRINET portal"
/ip hotspot walled-garden add dst-host=www.trinetpay.online comment="TRINET portal www"
/ip hotspot walled-garden add dst-host=palmpesa.drmlelwa.co.tz comment="PalmPesa payment API"

# These are the plans. Laravel selects the profile, then creates one WiFi token.
# Adjust session-timeout/rate-limit to your real business rules.
# /ip hotspot user profile add name=Starter-50 rate-limit=1M/1M session-timeout=10m shared-users=1
# /ip hotspot user profile add name=Bronze-1Hour rate-limit=1M/1M session-timeout=6h shared-users=1
# /ip hotspot user profile add name=Silver-1Day rate-limit=2M/2M session-timeout=1d shared-users=1
# /ip hotspot user profile add name=Gold-7Days rate-limit=5M/5M session-timeout=7d shared-users=1

# API must be enabled because Laravel creates Hotspot users through port 8728.
/ip service enable api
/ip service set api port=8728
