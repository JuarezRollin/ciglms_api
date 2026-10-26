<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guarantee_letters', function (Blueprint $table) {
            $table->id('id');
            $table->foreignId('office_id')->constrained('offices');

            /* GL CREATION - Owned by CRD */
            // Creation
            $table->string('gl_no')->unique()->index();
            $table->date('gl_date');
            $table->foreignId('client_id')->constrained('clients');
            $table->foreignId('beneficiary_id')->constrained('beneficiaries');
            $table->string('address');
            $table->foreignId('relationship_id')->constrained('relationships')->default(1); // 1 - Self

            $table->foreignId('service_provider_id')->constrained('service_providers');
            $table->foreignId('branch_id')->nullable()->constrained('branches'); // Only if applicable such as Pharmacies

            $table->foreignId('category_id')->constrained('categories');

            $table->foreignId('assistance_type_id')->constrained('assistance_types');
            $table->foreignId('status_id')->constrained('statuses');

            $table->decimal('gl_amount', 12, 2);
            $table->date('gl_validity');

            $table->foreignId('signatory_id')->constrained('signatories');
            $table->string('signed_pdf_path')->nullable();
            $table->string('signed_pdf_filename')->nullable();
            $table->datetime('signed_at')->nullable();

            $table->foreignId('swo_id')->constrained('swos');
            $table->foreignId('referring_party_id')->nullable()->constrained('referring_parties');

            $table->foreignId('created_by')->constrained('users');

            // Review by CRD
            $table->foreignId('reviewed_by_creation')->nullable()->constrained('users');
            $table->datetime('date_reviewed_by_creation')->nullable();

            // Approved by CRD
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->datetime('date_approved')->nullable();

            // Released by CRD
            $table->foreignId('released_by')->nullable()->constrained('users');
            $table->datetime('date_released')->nullable();

            // Endorsed by CRD
            $table->foreignId('endorsed_by')->nullable()->constrained('users');
            $table->datetime('date_endorsed')->nullable();

            // Optional: On Hold by CRD
            $table->foreignId('onhold_by')->nullable()->constrained('users');
            $table->datetime('date_onhold')->nullable();

            // Optional: Cancelled by CRD
            $table->foreignId('cancelled_by_creation')->nullable()->constrained('users');
            $table->datetime('date_cancelled_by_creation')->nullable();

            $table->text('creation_remarks')->nullable();

            /* GL PROCESSING - Owned by RMCD */
            // Receiving
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->datetime('date_received_gl')->nullable();
            $table->foreignId('soa_received_by')->nullable()->constrained('users');
            $table->datetime('date_received_soa')->nullable();
            $table->decimal('obligation_amount', 12, 2)->nullable();
            $table->foreignId('pulled_out_by')->nullable()->constrained('users');
            $table->datetime('date_pulled_out')->nullable();

            // Voucher Creation
            $table->foreignId('fund_source_id')->nullable()->constrained('fund_sources');
            $table->datetime('date_fwd_to_reviewer')->nullable();

            // Reviewer
            $table->foreignId('reviewed_by_rmcd')->nullable()->constrained('users');
            $table->foreignId('box_a_reviewed_by')->nullable()->constrained('users');

            // Outgoing
            $table->datetime('date_fwd_to_dc')->nullable();
            $table->datetime('date_fwd_to_dir_ors')->nullable();
            $table->datetime('date_fwd_to_budget')->nullable();
            $table->datetime('date_fwd_to_actg')->nullable();
            $table->datetime('date_fwd_to_dir_dv')->nullable();
            $table->datetime('date_fwd_to_dir_fs')->nullable();
            $table->datetime('date_fwd_to_cash')->nullable();

            // Optional: Cancelled by RMCD
            $table->foreignId('cancelled_by_rmcd')->nullable()->constrained('users');
            $table->datetime('date_cancelled_by_rmcd')->nullable();

            // Others
            $table->string('norsa_no')->nullable();
            $table->text('processing_remarks')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guarantee_letters');
    }
};
