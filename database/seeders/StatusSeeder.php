<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Seeder;

class StatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            Status::FOR_REVIEW_BY_CRD_ID => 'For Review by CRD',
            Status::APPROVED_ID => 'Approved',
            Status::RELEASED_ID => 'Released',
            Status::ON_HOLD_ID => 'On Hold',
            Status::CANCELLED_BY_CRD_ID => 'Cancelled by CRD',
            Status::FOR_ENDORSEMENT_ID => 'For Endorsement',
            Status::AWAITING_SOA_ID => 'Awaiting SOA',
            Status::FOR_PULLOUT_ID => 'For Pullout',
            Status::FOR_CREATION_ID => 'For Creation',
            Status::FOR_COMPLIANCE_ID => 'For Compliance',
            Status::FOR_REVIEW_BY_RMCD_ID => 'For Review by RMCD',
            Status::ON_PROCESS_ID => 'On Process',
            Status::FOR_OBLIGATION_ID => 'For Obligation',
            Status::FOR_PRE_AUDIT_ID => 'For Pre-Audit',
            Status::FOR_CREDITING_ID => 'For Crediting',
            Status::PAID_ID => 'Paid',
            Status::CANCELLED_BY_RMCD_ID => 'Cancelled by RMCD',
        ];

        foreach ($statuses as $id => $name) {
            if (Status::query()->whereKey($id)->doesntExist()) {
                Status::query()->forceCreate(['id' => $id, 'name' => $name]);
            }
        }
    }
}
