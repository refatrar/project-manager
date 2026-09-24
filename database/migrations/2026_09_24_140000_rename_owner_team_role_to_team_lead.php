<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The team-module role tier drops from 3 (Owner/Admin/Member) to 2
     * (Team Lead/Member). `admin`-role accounts are demoted to `member` —
     * a project-scoped "Project Lead" assignment replaces what that tier
     * was standing in for (see `add_project_lead_id_to_projects_table`).
     * `owner` is renamed to `team_lead` throughout: it was always "the
     * team's one full administrator," just mislabeled.
     */
    public function up(): void
    {
        DB::table('team_members')->where('role', 'admin')->update(['role' => 'member']);
        DB::table('team_invitations')->where('role', 'admin')->update(['role' => 'member']);

        DB::table('team_members')->where('role', 'owner')->update(['role' => 'team_lead']);
        DB::table('team_invitations')->where('role', 'owner')->update(['role' => 'team_lead']);

        DB::table('roles')
            ->where('guard_name', 'web')
            ->whereNull('team_id')
            ->where('slug', 'owner')
            ->update(['slug' => 'team_lead', 'name' => 'Team Lead']);

        $adminRoleIds = DB::table('roles')
            ->where('guard_name', 'web')
            ->whereNull('team_id')
            ->where('slug', 'admin')
            ->pluck('id');

        if ($adminRoleIds->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('role_id', $adminRoleIds)->delete();
            DB::table('model_has_roles')->whereIn('role_id', $adminRoleIds)->delete();
            DB::table('roles')->whereIn('id', $adminRoleIds)->delete();
        }
    }

    /**
     * Best-effort, not fully reversible: which `member` rows were
     * demoted from `admin` is not recorded, so they stay `member` on
     * rollback. The slug/name rename and the `admin` system role's
     * removal are reversed.
     */
    public function down(): void
    {
        DB::table('roles')
            ->where('guard_name', 'web')
            ->whereNull('team_id')
            ->where('slug', 'team_lead')
            ->update(['slug' => 'owner', 'name' => 'Project Lead']);

        DB::table('roles')->updateOrInsert(
            ['guard_name' => 'web', 'team_id' => null, 'slug' => 'admin'],
            ['name' => 'Team Lead', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
        );

        DB::table('team_members')->where('role', 'team_lead')->update(['role' => 'owner']);
        DB::table('team_invitations')->where('role', 'team_lead')->update(['role' => 'owner']);
    }
};
