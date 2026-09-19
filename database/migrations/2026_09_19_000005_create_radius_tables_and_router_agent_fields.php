<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Router connectivity, version 2.
 *
 * Routers used to be reached by the server, which cannot work when the router sits
 * behind NAT. Now every router connects OUT to the platform:
 *
 *   - FreeRADIUS authenticates customers against the rad* tables below. A payment or
 *     voucher redemption just writes rows here, no call to the router is needed.
 *   - A small script on the router polls the platform every minute (heartbeat plus
 *     commands), see router_commands.
 *
 * The rad* tables follow the standard FreeRADIUS SQL schema so its "sql" module works
 * unchanged. tenant_id and source on radcheck/radreply are our own additions, FreeRADIUS
 * ignores extra columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radcheck', function (Blueprint $table) {
            $table->id();
            $table->string('username', 64)->default('')->index();
            $table->string('attribute', 64)->default('');
            $table->char('op', 2)->default('==');
            $table->string('value', 253)->default('');
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('source', 60)->nullable()->index();
        });

        Schema::create('radreply', function (Blueprint $table) {
            $table->id();
            $table->string('username', 64)->default('')->index();
            $table->string('attribute', 64)->default('');
            $table->char('op', 2)->default('=');
            $table->string('value', 253)->default('');
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('source', 60)->nullable()->index();
        });

        Schema::create('radusergroup', function (Blueprint $table) {
            $table->string('username', 64)->default('')->index();
            $table->string('groupname', 64)->default('');
            $table->integer('priority')->default(1);
        });

        Schema::create('radgroupcheck', function (Blueprint $table) {
            $table->id();
            $table->string('groupname', 64)->default('')->index();
            $table->string('attribute', 64)->default('');
            $table->char('op', 2)->default('==');
            $table->string('value', 253)->default('');
        });

        Schema::create('radgroupreply', function (Blueprint $table) {
            $table->id();
            $table->string('groupname', 64)->default('')->index();
            $table->string('attribute', 64)->default('');
            $table->char('op', 2)->default('=');
            $table->string('value', 253)->default('');
        });

        Schema::create('radacct', function (Blueprint $table) {
            $table->bigIncrements('radacctid');
            $table->string('acctsessionid', 64)->default('')->index();
            $table->string('acctuniqueid', 32)->default('')->unique();
            $table->string('username', 64)->default('')->index();
            $table->string('groupname', 64)->default('');
            $table->string('realm', 64)->nullable()->default('');
            $table->string('nasipaddress', 15)->default('')->index();
            $table->string('nasportid', 32)->nullable();
            $table->string('nasporttype', 32)->nullable();
            $table->dateTime('acctstarttime')->nullable()->index();
            $table->dateTime('acctupdatetime')->nullable();
            $table->dateTime('acctstoptime')->nullable()->index();
            $table->integer('acctinterval')->nullable();
            $table->unsignedInteger('acctsessiontime')->nullable();
            $table->string('acctauthentic', 32)->nullable();
            $table->string('connectinfo_start', 128)->nullable();
            $table->string('connectinfo_stop', 128)->nullable();
            $table->bigInteger('acctinputoctets')->nullable();
            $table->bigInteger('acctoutputoctets')->nullable();
            $table->string('calledstationid', 50)->default('');
            $table->string('callingstationid', 50)->default('');
            $table->string('acctterminatecause', 32)->default('');
            $table->string('servicetype', 32)->nullable();
            $table->string('framedprotocol', 32)->nullable();
            $table->string('framedipaddress', 15)->default('')->index();
            $table->string('framedipv6address', 45)->default('');
            $table->string('framedipv6prefix', 45)->default('');
            $table->string('framedinterfaceid', 44)->default('');
            $table->string('delegatedipv6prefix', 45)->default('');
            $table->string('class', 64)->nullable();
        });

        Schema::create('radpostauth', function (Blueprint $table) {
            $table->id();
            $table->string('username', 64)->default('')->index();
            $table->string('pass', 64)->default('');
            $table->string('reply', 32)->default('');
            $table->string('calledstationid', 50)->default('');
            $table->string('callingstationid', 50)->default('');
            $table->timestamp('authdate', 6)->useCurrent();
        });

        Schema::create('nas', function (Blueprint $table) {
            $table->id();
            $table->string('nasname', 128)->index();
            $table->string('shortname', 32)->nullable();
            $table->string('type', 30)->default('other');
            $table->integer('ports')->nullable();
            $table->string('secret', 60)->default('secret');
            $table->string('server', 64)->nullable();
            $table->string('community', 50)->nullable();
            $table->string('description', 200)->default('RADIUS Client');
        });

        Schema::table('tenant_routers', function (Blueprint $table) {
            // api    = the platform logs in to the router (old way, needs a reachable router)
            // radius = the router connects out to the platform (works behind NAT, any RouterOS)
            $table->string('auth_mode', 10)->default('api')->after('name');

            // Secret used in the agent poll URL. Separate from provision_token, which only
            // serves the one-time setup script.
            $table->string('agent_token', 64)->nullable()->unique()->after('provision_token');

            $table->string('routeros_version', 40)->nullable();
            $table->string('public_ip', 45)->nullable();
            $table->unsignedInteger('active_users')->default(0);
            $table->string('router_uptime', 40)->nullable();
            $table->string('provision_note', 255)->nullable();
        });

        // Routers set up with the new script never expose an API, so these are optional now.
        Schema::table('tenant_routers', function (Blueprint $table) {
            $table->string('router_ip')->nullable()->change();
            $table->string('username')->nullable()->change();
            $table->text('password')->nullable()->change();
        });

        // Things the platform wants a router to do. The router fetches them on its next poll.
        Schema::create('router_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('router_id')->constrained('tenant_routers')->cascadeOnDelete();
            $table->string('type', 20);                         // reboot, kick_user, kick_all
            $table->json('payload')->nullable();
            $table->string('status', 12)->default('pending');   // pending, delivered
            $table->timestamp('delivered_at')->nullable();
            $table->string('requested_by', 80)->nullable();
            $table->timestamps();

            $table->index(['router_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('router_commands');

        Schema::table('tenant_routers', function (Blueprint $table) {
            $table->dropUnique(['agent_token']);
            $table->dropColumn([
                'auth_mode', 'agent_token', 'routeros_version', 'public_ip',
                'active_users', 'router_uptime', 'provision_note',
            ]);
        });

        foreach (['nas', 'radpostauth', 'radacct', 'radgroupreply', 'radgroupcheck', 'radusergroup', 'radreply', 'radcheck'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
