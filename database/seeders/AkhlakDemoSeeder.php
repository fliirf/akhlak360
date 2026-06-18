<?php

namespace Database\Seeders;

use App\Models\AppNotification;
use App\Models\AssessmentAssignment;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentResponse;
use App\Models\AssessmentResult;
use App\Models\AssessmentWeight;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\HrisSyncLog;
use App\Models\IdpRecommendation;
use App\Models\PeerApproval;
use App\Models\Position;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class AkhlakDemoSeeder extends Seeder
{
    private const CORE_VALUES = [
        'Amanah' => 'Menjaga kepercayaan dan bertanggung jawab atas tugas.',
        'Kompeten' => 'Terus belajar dan meningkatkan kapabilitas.',
        'Harmonis' => 'Peduli dan menghargai perbedaan.',
        'Loyal' => 'Berdedikasi dan mengutamakan kepentingan bangsa/perusahaan.',
        'Adaptif' => 'Berinovasi dan antusias menghadapi perubahan.',
        'Kolaboratif' => 'Membangun kerja sama yang sinergis.',
    ];

    public function run(): void
    {
        $users = $this->seedUsers();
        $departments = $this->seedDepartments();
        $positions = $this->seedPositions();
        $employees = $this->seedEmployees($users, $departments, $positions);
        $historicalPeriod = $this->seedHistoricalAssessmentPeriod();
        $period = $this->seedAssessmentPeriod();

        $this->seedWeights($historicalPeriod);
        $this->seedWeights($period);
        $this->seedPeerApprovals($period, $employees);
        $historicalAssignments = $this->seedAssignments($historicalPeriod, $employees);
        $assignments = $this->seedAssignments($period, $employees);
        $this->seedResponses($historicalAssignments);
        $this->seedResponses($assignments);
        $this->seedResults($historicalPeriod, $employees, 1);
        $this->seedResults($period, $employees, 0);
        $this->seedIdpRecommendations($historicalPeriod, $employees);
        $this->seedIdpRecommendations($period, $employees);
        $this->seedNotifications($users, $period);
        $this->seedAuditLogs($users);
        $this->seedHrisSyncLogs($users);
    }

    /**
     * @return array<string, User>
     */
    private function seedUsers(): array
    {
        $password = Hash::make('password');

        $records = [
            'admin_hr' => ['name' => 'Admin Human Capital', 'email' => 'admin_hr@example.com', 'role' => 'admin_hr'],
            'supervisor' => ['name' => 'Budi Supervisor', 'email' => 'supervisor@example.com', 'role' => 'supervisor'],
            'employee' => ['name' => 'Sari Employee', 'email' => 'employee@example.com', 'role' => 'employee'],
            'management' => ['name' => 'Dewi Management', 'email' => 'management@example.com', 'role' => 'management'],
            'it_admin' => ['name' => 'Raka IT Admin', 'email' => 'it@example.com', 'role' => 'it_admin'],
        ];

        return collect($records)
            ->mapWithKeys(fn (array $record, string $key) => [
                $key => User::create([
                    ...$record,
                    'password' => $password,
                    'email_verified_at' => now(),
                    'sso_provider' => 'simulated_sso',
                    'sso_id' => 'sso-'.$key,
                    'last_login_at' => now()->subDays(strlen($key)),
                ]),
            ])
            ->all();
    }

    /**
     * @return Collection<string, Department>
     */
    private function seedDepartments(): Collection
    {
        return collect([
            ['name' => 'Human Capital', 'code' => 'HC'],
            ['name' => 'Operations', 'code' => 'OPS'],
            ['name' => 'Finance', 'code' => 'FIN'],
            ['name' => 'IT', 'code' => 'IT'],
            ['name' => 'Corporate Strategy', 'code' => 'STR'],
        ])->mapWithKeys(fn (array $department) => [
            $department['name'] => Department::create($department),
        ]);
    }

    /**
     * @return Collection<string, Position>
     */
    private function seedPositions(): Collection
    {
        return collect([
            ['name' => 'Staff', 'level' => 'L1'],
            ['name' => 'Senior Staff', 'level' => 'L2'],
            ['name' => 'Supervisor', 'level' => 'L3'],
            ['name' => 'Manager', 'level' => 'L4'],
            ['name' => 'General Manager', 'level' => 'L5'],
        ])->mapWithKeys(fn (array $position) => [
            $position['name'] => Position::create($position),
        ]);
    }

    /**
     * @param  array<string, User>  $users
     * @return Collection<int, Employee>
     */
    private function seedEmployees(array $users, Collection $departments, Collection $positions): Collection
    {
        $employeeRecords = [
            ['number' => 'EN-0001', 'name' => 'Dewi Management', 'email' => 'management@example.com', 'department' => 'Corporate Strategy', 'position' => 'General Manager', 'user' => 'management'],
            ['number' => 'EN-0002', 'name' => 'Admin Human Capital', 'email' => 'admin_hr@example.com', 'department' => 'Human Capital', 'position' => 'Manager', 'supervisor' => 'EN-0001', 'user' => 'admin_hr'],
            ['number' => 'EN-0003', 'name' => 'Budi Supervisor', 'email' => 'supervisor@example.com', 'department' => 'Operations', 'position' => 'Supervisor', 'supervisor' => 'EN-0001', 'user' => 'supervisor'],
            ['number' => 'EN-0004', 'name' => 'Raka IT Admin', 'email' => 'it@example.com', 'department' => 'IT', 'position' => 'Manager', 'supervisor' => 'EN-0001', 'user' => 'it_admin'],
            ['number' => 'EN-0005', 'name' => 'Sari Employee', 'email' => 'employee@example.com', 'department' => 'Operations', 'position' => 'Staff', 'supervisor' => 'EN-0003', 'user' => 'employee'],
            ['number' => 'EN-0006', 'name' => 'Andi Pratama', 'email' => 'andi.pratama@example.com', 'department' => 'Operations', 'position' => 'Senior Staff', 'supervisor' => 'EN-0003'],
            ['number' => 'EN-0007', 'name' => 'Maya Lestari', 'email' => 'maya.lestari@example.com', 'department' => 'Operations', 'position' => 'Staff', 'supervisor' => 'EN-0003'],
            ['number' => 'EN-0008', 'name' => 'Fajar Nugroho', 'email' => 'fajar.nugroho@example.com', 'department' => 'Operations', 'position' => 'Staff', 'supervisor' => 'EN-0003'],
            ['number' => 'EN-0009', 'name' => 'Nina Kartika', 'email' => 'nina.kartika@example.com', 'department' => 'Human Capital', 'position' => 'Supervisor', 'supervisor' => 'EN-0002'],
            ['number' => 'EN-0010', 'name' => 'Rini Astuti', 'email' => 'rini.astuti@example.com', 'department' => 'Human Capital', 'position' => 'Staff', 'supervisor' => 'EN-0009'],
            ['number' => 'EN-0011', 'name' => 'Galih Saputra', 'email' => 'galih.saputra@example.com', 'department' => 'Human Capital', 'position' => 'Senior Staff', 'supervisor' => 'EN-0009'],
            ['number' => 'EN-0012', 'name' => 'Agus Permana', 'email' => 'agus.permana@example.com', 'department' => 'Finance', 'position' => 'Manager', 'supervisor' => 'EN-0001'],
            ['number' => 'EN-0013', 'name' => 'Putri Maharani', 'email' => 'putri.maharani@example.com', 'department' => 'Finance', 'position' => 'Supervisor', 'supervisor' => 'EN-0012'],
            ['number' => 'EN-0014', 'name' => 'Joko Santoso', 'email' => 'joko.santoso@example.com', 'department' => 'Finance', 'position' => 'Staff', 'supervisor' => 'EN-0013'],
            ['number' => 'EN-0015', 'name' => 'Lina Wibowo', 'email' => 'lina.wibowo@example.com', 'department' => 'Finance', 'position' => 'Senior Staff', 'supervisor' => 'EN-0013'],
            ['number' => 'EN-0016', 'name' => 'Dimas Arya', 'email' => 'dimas.arya@example.com', 'department' => 'IT', 'position' => 'Supervisor', 'supervisor' => 'EN-0004'],
            ['number' => 'EN-0017', 'name' => 'Citra Amalia', 'email' => 'citra.amalia@example.com', 'department' => 'IT', 'position' => 'Staff', 'supervisor' => 'EN-0016'],
            ['number' => 'EN-0018', 'name' => 'Yusuf Hidayat', 'email' => 'yusuf.hidayat@example.com', 'department' => 'IT', 'position' => 'Senior Staff', 'supervisor' => 'EN-0016'],
            ['number' => 'EN-0019', 'name' => 'Tania Safitri', 'email' => 'tania.safitri@example.com', 'department' => 'Corporate Strategy', 'position' => 'Supervisor', 'supervisor' => 'EN-0001'],
            ['number' => 'EN-0020', 'name' => 'Reza Mahendra', 'email' => 'reza.mahendra@example.com', 'department' => 'Corporate Strategy', 'position' => 'Staff', 'supervisor' => 'EN-0019'],
        ];

        $employeesByNumber = collect();

        foreach ($employeeRecords as $record) {
            $employeesByNumber->put($record['number'], Employee::create([
                'user_id' => isset($record['user']) ? $users[$record['user']]->id : null,
                'department_id' => $departments[$record['department']]->id,
                'position_id' => $positions[$record['position']]->id,
                'employee_number' => $record['number'],
                'name' => $record['name'],
                'email' => $record['email'],
                'employment_status' => 'active',
                'hris_external_id' => 'HRIS-'.$record['number'],
                'last_synced_at' => now()->subHours((int) substr($record['number'], -2)),
            ]));
        }

        foreach ($employeeRecords as $record) {
            if (! isset($record['supervisor'])) {
                continue;
            }

            $employeesByNumber[$record['number']]->update([
                'supervisor_id' => $employeesByNumber[$record['supervisor']]->id,
            ]);
        }

        return $employeesByNumber->values();
    }

    private function seedAssessmentPeriod(): AssessmentPeriod
    {
        $startDate = Carbon::create(2026, 6, 16);

        return AssessmentPeriod::create([
            'name' => 'Semester 1 2026',
            'semester' => 'Semester 1',
            'year' => 2026,
            'start_date' => $startDate,
            'end_date' => $startDate->copy()->addDays(13),
            'status' => 'active',
            'threshold_score' => 3.00,
        ]);
    }

    private function seedHistoricalAssessmentPeriod(): AssessmentPeriod
    {
        return AssessmentPeriod::create([
            'name' => 'Semester 2 2025',
            'semester' => 'Semester 2',
            'year' => 2025,
            'start_date' => Carbon::create(2025, 11, 1),
            'end_date' => Carbon::create(2025, 11, 14),
            'status' => 'closed',
            'threshold_score' => 3.00,
        ]);
    }

    private function seedWeights(AssessmentPeriod $period): void
    {
        collect([
            'supervisor' => 40,
            'peer' => 20,
            'subordinate' => 30,
            'self' => 10,
        ])->each(fn (int $weight, string $type) => AssessmentWeight::create([
            'assessment_period_id' => $period->id,
            'assessor_type' => $type,
            'weight' => $weight,
        ]));
    }

    private function seedPeerApprovals(AssessmentPeriod $period, Collection $employees): void
    {
        foreach ($employees->whereNotNull('supervisor_id')->take(12) as $index => $employee) {
            $peer = $employees
                ->where('department_id', $employee->department_id)
                ->where('id', '!=', $employee->id)
                ->first()
                ?? $employees->where('id', '!=', $employee->id)->first();

            PeerApproval::create([
                'assessment_period_id' => $period->id,
                'employee_id' => $employee->id,
                'peer_employee_id' => $peer?->id,
                'supervisor_employee_id' => $employee->supervisor_id,
                'status' => ['pending', 'approved', 'rejected'][$index % 3],
                'notes' => $index % 3 === 2 ? 'Reviewer perlu disesuaikan karena konflik penugasan.' : null,
                'approved_at' => $index % 3 === 1 ? now()->subDays($index) : null,
            ]);
        }
    }

    /**
     * @return Collection<int, AssessmentAssignment>
     */
    private function seedAssignments(AssessmentPeriod $period, Collection $employees): Collection
    {
        $assignments = collect();

        foreach ($employees as $employee) {
            $assignments->push($this->createAssignment($period, $employee, $employee, 'self'));

            if ($employee->supervisor_id) {
                $assignments->push($this->createAssignment(
                    $period,
                    Employee::findOrFail($employee->supervisor_id),
                    $employee,
                    'supervisor'
                ));
            }

            $peers = $employees
                ->where('department_id', $employee->department_id)
                ->where('id', '!=', $employee->id)
                ->values()
                ->take($employee->id % 2 === 0 ? 3 : 2);

            foreach ($peers as $peer) {
                $assignments->push($this->createAssignment($period, $peer, $employee, 'peer'));
            }

            foreach ($employee->subordinates as $subordinate) {
                $assignments->push($this->createAssignment($period, $subordinate, $employee, 'subordinate'));
            }
        }

        return $assignments->filter()->values();
    }

    private function createAssignment(
        AssessmentPeriod $period,
        Employee $assessor,
        Employee $assessee,
        string $type
    ): ?AssessmentAssignment {
        if ($assessor->id === $assessee->id && $type !== 'self') {
            return null;
        }

        return AssessmentAssignment::firstOrCreate([
            'assessment_period_id' => $period->id,
            'assessor_employee_id' => $assessor->id,
            'assessee_employee_id' => $assessee->id,
            'assessor_type' => $type,
        ], [
            'status' => 'pending',
        ]);
    }

    private function seedResponses(Collection $assignments): void
    {
        foreach ($assignments as $index => $assignment) {
            if ($index % 3 === 0) {
                continue;
            }

            $assignment->update([
                'status' => 'submitted',
                'submitted_at' => now()->subDays($index % 10),
            ]);

            foreach (self::CORE_VALUES as $coreValue => $indicator) {
                AssessmentResponse::create([
                    'assessment_assignment_id' => $assignment->id,
                    'core_value' => $coreValue,
                    'indicator' => $indicator,
                    'score' => 3 + (($assignment->id + strlen($coreValue)) % 3),
                ]);
            }
        }
    }

    private function seedResults(AssessmentPeriod $period, Collection $employees, int $periodOffset): void
    {
        $finalScores = [2.55, 2.75, 2.92, 3.05, 3.18, 3.32, 3.48, 3.62, 3.78, 3.92, 4.08, 4.22, 4.38, 4.52, 4.68, 3.26, 3.71, 4.02, 2.86, 4.44];
        $gapScores = [0.80, -0.75, 0.15, 0.55, -0.20, -0.60, 0.35, 0.05];

        foreach ($employees as $index => $employee) {
            $baseFinal = $finalScores[($index + $periodOffset * 3) % count($finalScores)];
            $finalScore = round(max(1, min(5, $baseFinal - ($periodOffset * 0.18))), 2);
            $gapScore = $gapScores[($index + $periodOffset) % count($gapScores)];
            $othersScore = round(max(1, min(5, $finalScore - 0.04)), 2);
            $selfScore = round(max(1, min(5, $othersScore + $gapScore)), 2);
            $scores = collect(array_keys(self::CORE_VALUES))
                ->mapWithKeys(fn (string $value, int $coreIndex) => [
                    strtolower($value).'_score' => round(max(1, min(5, $finalScore + ((($index + $coreIndex) % 5) - 2) * 0.12)), 2),
                ]);

            AssessmentResult::create([
                'assessment_period_id' => $period->id,
                'employee_id' => $employee->id,
                ...$scores->all(),
                'self_score' => $selfScore,
                'others_score' => $othersScore,
                'gap_score' => $gapScore,
                'final_score' => $finalScore,
                'category' => match (true) {
                    $finalScore < 3.00 => 'Perlu Pengembangan',
                    $finalScore < 3.75 => 'Cukup',
                    $finalScore < 4.50 => 'Baik',
                    default => 'Sangat Baik',
                },
                'talent_mapping_category' => match (true) {
                    $finalScore >= 4.50 && $gapScore >= -0.50 && $gapScore <= 0.50 => 'High Potential',
                    $finalScore >= 3.75 => 'Solid Contributor',
                    $finalScore >= 3.00 => 'Core Contributor',
                    default => 'Need Development',
                },
            ]);
        }
    }

    private function seedIdpRecommendations(AssessmentPeriod $period, Collection $employees): void
    {
        $weakValues = ['Adaptif', 'Harmonis', 'Kompeten', 'Kolaboratif'];

        foreach ($employees->take(8) as $index => $employee) {
            IdpRecommendation::create([
                'assessment_period_id' => $period->id,
                'employee_id' => $employee->id,
                'weakest_core_value' => $weakValues[$index % count($weakValues)],
                'recommendation' => 'Mengikuti coaching dan pembelajaran terarah untuk memperkuat perilaku '.$weakValues[$index % count($weakValues)].'.',
                'action_plan' => $index % 2 === 0 ? 'Diskusi bulanan dengan atasan dan menyelesaikan modul pengembangan terkait.' : null,
                'status' => ['draft', 'approved', 'in_progress', 'completed'][$index % 4],
                'due_date' => Carbon::create(2026, 7, 15)->addDays($index * 3),
            ]);
        }
    }

    /**
     * @param  array<string, User>  $users
     */
    private function seedNotifications(array $users, AssessmentPeriod $period): void
    {
        $notifications = [
            ['user' => 'admin_hr', 'title' => 'Periode aktif', 'type' => 'system', 'message' => $period->name.' telah aktif untuk seluruh pegawai.', 'destination_url' => '/assessment-cycle/periods'],
            ['user' => 'supervisor', 'title' => 'Persetujuan peer reviewer', 'type' => 'assessment_reminder', 'message' => 'Silakan pantau progres penilaian tim Anda.', 'destination_url' => '/assessment-cycle/peer-approval'],
            ['user' => 'employee', 'title' => 'Form penilaian tersedia', 'type' => 'assessment_reminder', 'message' => 'Anda memiliki form self assessment dan peer review yang perlu diselesaikan.', 'destination_url' => '/assessment/pending'],
            ['user' => 'management', 'title' => 'Dashboard manajemen siap', 'type' => 'result', 'message' => 'Ringkasan skor AKHLAK semester ini sudah tersedia.', 'destination_url' => '/management/dashboard'],
            ['user' => 'it_admin', 'title' => 'Sinkronisasi HRIS selesai', 'type' => 'system', 'message' => 'Import CSV demo berhasil diproses.', 'destination_url' => '/master-data/hris-sync'],
        ];

        foreach ($notifications as $index => $notification) {
            AppNotification::create([
                'user_id' => $users[$notification['user']]->id,
                'title' => $notification['title'],
                'message' => $notification['message'],
                'type' => $notification['type'],
                'destination_url' => $notification['destination_url'],
                'read_at' => $index % 2 === 0 ? null : now()->subHours($index),
            ]);
        }
    }

    /**
     * @param  array<string, User>  $users
     */
    private function seedAuditLogs(array $users): void
    {
        $logs = [
            ['user' => 'admin_hr', 'action' => 'create', 'module' => 'assessment_period', 'description' => 'Membuat periode Semester 1 2026.'],
            ['user' => 'admin_hr', 'action' => 'import', 'module' => 'hris', 'description' => 'Import CSV master pegawai demo.'],
            ['user' => 'supervisor', 'action' => 'approve', 'module' => 'peer_approval', 'description' => 'Menyetujui daftar peer reviewer tim Operations.'],
            ['user' => 'employee', 'action' => 'submit', 'module' => 'assessment', 'description' => 'Mengirim self assessment.'],
            ['user' => 'management', 'action' => 'view', 'module' => 'dashboard', 'description' => 'Melihat dashboard analitik manajemen.'],
            ['user' => 'it_admin', 'action' => 'simulate_sso', 'module' => 'authentication', 'description' => 'Melakukan simulasi login SSO.'],
        ];

        foreach ($logs as $index => $log) {
            AuditLog::create([
                'user_id' => $users[$log['user']]->id,
                'action' => $log['action'],
                'module' => $log['module'],
                'description' => $log['description'],
                'ip_address' => '127.0.0.'.($index + 1),
                'user_agent' => 'AKHLAK360 Demo Seeder',
            ]);
        }
    }

    /**
     * @param  array<string, User>  $users
     */
    private function seedHrisSyncLogs(array $users): void
    {
        HrisSyncLog::create([
            'sync_type' => 'import_csv',
            'status' => 'success',
            'total_records' => 20,
            'success_records' => 20,
            'failed_records' => 0,
            'message' => 'Import CSV demo pegawai berhasil.',
            'synced_by' => $users['admin_hr']->id,
        ]);

        HrisSyncLog::create([
            'sync_type' => 'manual_sync',
            'status' => 'failed',
            'total_records' => 3,
            'success_records' => 1,
            'failed_records' => 2,
            'message' => 'Simulasi kegagalan validasi email HRIS.',
            'synced_by' => $users['it_admin']->id,
        ]);
    }
}
