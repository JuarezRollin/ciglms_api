<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AssistanceTypeController;
use App\Http\Controllers\Api\BeneficiaryController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ComplianceController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FundSourceController;
use App\Http\Controllers\Api\GlTransmittalController;
use App\Http\Controllers\Api\GuaranteeLetterController;
use App\Http\Controllers\Api\GuaranteeLetterDocumentController;
use App\Http\Controllers\Api\OfficeController;
use App\Http\Controllers\Api\ReferringPartyController;
use App\Http\Controllers\Api\RegionController;
use App\Http\Controllers\Api\RelationshipController;
use App\Http\Controllers\Api\ServiceProviderController;
use App\Http\Controllers\Api\SignatoryController;
use App\Http\Controllers\Api\SocialWorkerController;
use App\Http\Controllers\Api\StatusController;
use App\Http\Controllers\Api\VoucherCreationController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;



Route::group(['prefix' => 'v1', 'middleware' => 'api'], function () {
    // Public Api
    Route::post('/login', [AuthController::class, 'login']);
    // Private Api
    Route::middleware(['auth:api', 'throttle:120,1'])->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('service-providers/dropdown', [ServiceProviderController::class, 'dropdown'])->name('service-providers.dropdown');
        Route::get('relationships/dropdown', [RelationshipController::class, 'dropdown'])->name('relationships.dropdown');
        Route::get('categories/dropdown', [CategoryController::class, 'dropdown'])->name('categories.dropdown');
        Route::get('assistance-types/dropdown', [AssistanceTypeController::class, 'dropdown'])->name('assistance-types.dropdown');
        Route::get('clients/dropdown', [ClientController::class, 'dropdown'])->name('clients.dropdown');
        Route::get('beneficiaries/dropdown', [BeneficiaryController::class, 'dropdown'])->name('beneficiaries.dropdown');
        Route::get('branches/dropdown', [BranchController::class, 'dropdown'])->name('branches.dropdown');
        Route::get('offices/dropdown', [BranchController::class, 'dropdown'])->name('offices.dropdown');
        Route::get('region/dropdown', [RegionController::class, 'dropdown'])->name('region.dropdown');
        Route::get('swo/dropdown', [SocialWorkerController::class, 'dropdown'])->name('swo.dropdown');
        Route::get('signatories/dropdown', [SignatoryController::class, 'dropdown'])->name('signatories.dropdown');
        Route::get('signatories/routing-preview', [SignatoryController::class, 'routingPreview'])->name('signatories.routing-preview');
        Route::get('referring-party/dropdown', [ReferringPartyController::class, 'dropdown'])->name('referring-party.dropdown');

        Route::apiResource('accounts', AccountController::class)->except('destroy');
        Route::apiResource('referring-party', ReferringPartyController::class)->except('destroy');
        Route::apiResource('swo', SocialWorkerController::class)->except('destroy');
        Route::apiResource('service-providers', ServiceProviderController::class)->except('destroy');
        Route::apiResource('offices', OfficeController::class)->except('destroy');
        Route::apiResource('branches', BranchController::class)->except('destroy');
        Route::apiResource('clients', ClientController::class)->except('destroy');
        Route::apiResource('beneficiaries', BeneficiaryController::class)->except('destroy');
        Route::apiResource('fund-sources', FundSourceController::class)->except('destroy');
        Route::apiResource('assistance-types', AssistanceTypeController::class)->except('destroy');
        Route::apiResource('categories', CategoryController::class)->except('destroy');
        Route::apiResource('statuses', StatusController::class)->except('destroy');
        Route::apiResource('relationships', RelationshipController::class)->except('destroy');
        Route::apiResource('signatories', SignatoryController::class)->except('destroy');
        Route::apiResource('guarantee-letters', GuaranteeLetterController::class)->except('destroy');
        Route::apiResource('gl-transmittals', GlTransmittalController::class)->parameters(['gl-transmittals' => 'transmittal'])->except('destroy');
        Route::get('guarantee-letters/filter-options', [GuaranteeLetterController::class, 'listFilterOptions'])->name('guarantee-letters.filter-options');
        Route::get('gl-transmittals/eligible', [GlTransmittalController::class, 'eligible'])->name('gl-transmittals.eligible');
        Route::get('gl-transmittals/eligible/filter-options', [GlTransmittalController::class, 'eligibleFilterOptions'])->name('gl-transmittals.eligible-filter-options');
        Route::get('gl-transmittals/filter-options', [GlTransmittalController::class, 'filterOptions'])->name('gl-transmittals.filter-options');
        Route::get('gl-transmittals/{transmittal}/print', [GlTransmittalController::class, 'print'])->name('gl-transmittals.print');
        Route::post('gl-transmittals/{transmittal}/cancel', [GlTransmittalController::class, 'cancel'])->name('gl-transmittals.cancel');
        Route::post('gl-transmittals/{transmittal}/receive', [GlTransmittalController::class, 'receive'])->name('gl-transmittals.receive');
        Route::post('guarantee-letters/{guarantee_letter}/actions', [GuaranteeLetterController::class, 'processAction'])->name('guarantee-letters.actions');
        Route::get('guarantee-letters/{guarantee_letter}/audit-trail', [GuaranteeLetterController::class, 'auditTrail'])->name('guarantee-letters.audit-trail');
        Route::get('guarantee-letters/{guarantee_letter}/supporting-documents/download', [GuaranteeLetterDocumentController::class, 'download'])->name('guarantee-letters.supporting-documents.download');
        Route::get('guarantee-letters/{guarantee_letter}/supporting-documents/{document}', [GuaranteeLetterDocumentController::class, 'show'])->whereNumber('document')->name('guarantee-letters.supporting-documents.show');
        Route::post('accounts/change-password/{id}', [AccountController::class, 'updatePassword'])->name('accounts.updatePassword');
        Route::post('accounts/lock-account/{id}', [AccountController::class, 'deactivate'])->name('accounts.deactivate');
        
        // GL Creation
        Route::controller(GuaranteeLetterController::class)->prefix('gl_creation')->name('gl_creation.')->group(function (): void {
            Route::post('reviewed/approve-selected', 'approveSelected')->name('reviewed.approve-selected');
            Route::get('reviewed/filter-options', 'reviewFilterOptions')->name('reviewed.filter-options');
            Route::get('reviewed', 'getForReviewGL')->name('reviewed.getForReviewGL');
            Route::post('approval/release-selected', 'releaseSelected')->name('approval.release-selected');
            Route::get('approval/filter-options', 'approvalFilterOptions')->name('approval.filter-options');
            Route::get('approval', 'getForApprovalGL')->name('approval.getForApprovalGL');
            Route::get('released/filter-options', 'releasedFilterOptions')->name('released.filter-options');
            Route::get('released', 'getForReleaseGL')->name('released.getForReleaseGL');
            Route::get('endorsement/filter-options', 'endorsementFilterOptions')->name('endorsement.filter-options');
            Route::get('endorsement', 'getForEndorsementGL')->name('endorsement.getForEndorsementGL');
        });

        // voucher creation
        Route::controller(VoucherCreationController::class)->prefix('voucher-creation')->name('voucher-creation.')->group(function (): void {
            Route::get('reviewer-options', 'reviewerOptions')->name('reviewer-options');
            Route::get('batches', 'index')->name('batches.index');
            Route::get('batches/all', 'allIndex')->name('batches.all');
            Route::post('batches', 'store')->name('batches.store');
            Route::get('batches/{batch}', 'show')->name('batches.show');
            Route::get('batches/{batch}/audit-trail', 'auditTrail')->name('batches.audit-trail');
        });

        // for review
        Route::controller(VoucherCreationController::class)->prefix('voucher-review')->name('voucher-review.')->group(function (): void {
            Route::get('batches', 'reviewIndex')->name('batches.index');
            Route::get('batches/{batch}/print', 'printReviewPdf')->name('batches.print');
            Route::get('batches/{batch}/print-dv', 'printDv')->name('batches.print-dv');
            Route::get('batches/{batch}/print-combined', 'printCombined')->name('batches.print-combined');
            Route::get('batches/{batch}/print-transmittal', 'printTransmittal')->name('batches.print-transmittal');
            Route::post('batches/{batch}/forward-to-box-a', 'forwardToBoxA')->name('batches.forward-to-box-a');
            Route::post('batches/{batch}/return-for-compliance', 'returnForCompliance')->name('batches.return-for-compliance');
        });

        // compliance
        Route::controller(ComplianceController::class)->prefix('compliance')->name('compliance.')->group(function (): void {
            Route::get('access', 'access')->name('access');
            Route::get('/', 'index')->name('index');
            Route::get('{compliance}', 'show')->name('show');
            Route::post('{compliance}/resolve', 'resolve')->name('resolve');
        });

        // outgoing
        Route::controller(VoucherCreationController::class)->prefix('outgoing')->name('outgoing.')->group(function (): void {
            Route::prefix('on-process')->name('on-process.')->group(function (): void {
                Route::get('batches', 'onProcessIndex')->name('batches.index');
                Route::get('batches/{batch}', 'onProcessShow')->name('batches.show');
                Route::post('batches/{batch}/approve-box-a', 'approveBoxAReview')->name('batches.approve-box-a');
                Route::post('batches/{batch}/return-for-compliance', 'returnFromBoxAForCompliance')->name('batches.return-for-compliance');
                Route::post('batches/{batch}/forward-to-box-b', 'forwardToBoxB')->name('batches.forward-to-box-b');
            });

            Route::prefix('for-obligation')->name('for-obligation.')->group(function (): void {
                Route::get('batches', 'forObligationIndex')->name('batches.index');
                Route::get('batches/{batch}', 'forObligationShow')->name('batches.show');
                Route::post('batches/{batch}/forward-to-box-c', 'forwardToBoxC')->name('batches.forward-to-box-c');
                Route::post('batches/{batch}/return-for-compliance', 'returnFromBudgetForCompliance')->name('batches.return-for-compliance');
            });

            Route::prefix('for-pre-audit')->name('for-pre-audit.')->group(function (): void {
                Route::get('batches', 'forPreAuditIndex')->name('batches.index');
                Route::get('batches/{batch}', 'forPreAuditShow')->name('batches.show');
                Route::post('batches/{batch}/forward-to-box-d', 'forwardToBoxD')->name('batches.forward-to-box-d');
                Route::post('batches/{batch}/return-for-compliance', 'returnFromAccountingForCompliance')->name('batches.return-for-compliance');
            });

            Route::prefix('for-crediting')->name('for-crediting.')->group(function (): void {
                Route::get('batches', 'forCreditingIndex')->name('batches.index');
                Route::get('batches/{batch}', 'forCreditingShow')->name('batches.show');
                Route::post('batches/{batch}/tag-signed', 'tagSignedByCipDirector')->name('batches.tag-signed');
                Route::post('batches/{batch}/forward-to-cash', 'forwardToCash')->name('batches.forward-to-cash');
                Route::patch('batches/{batch}/financial-details', 'recordFinancialDetails')->name('batches.financial-details');
                Route::post('batches/{batch}/mark-as-paid', 'markAsPaid')->name('batches.mark-as-paid');
                Route::post('batches/{batch}/return-for-compliance', 'returnFromCashForCompliance')->name('batches.return-for-compliance');
            });
            Route::prefix('paid')->name('paid.')->group(function (): void {
                Route::get('batches', 'paidIndex')->name('batches.index');
                Route::get('batches/{batch}', 'paidShow')->name('batches.show');
            });
        });

        // Printing
        Route::get('guarantee-letters/printGl/{id}', [GuaranteeLetterController::class, 'printGL'])->name('guarantee-letters.printGL');

        Route::patch('service-providers/{service_provider}/status', [ServiceProviderController::class, 'updateStatus'])
            ->name('service-providers.update-status');
        Route::patch('branches/{branch}/status', [BranchController::class, 'updateStatus'])
            ->name('branches.update-status');
        Route::patch('fund-sources/{fund_source}/status', [FundSourceController::class, 'updateStatus'])
            ->name('fund-sources.update-status');
        Route::patch('assistance-types/{assistance_type}/status', [AssistanceTypeController::class, 'updateStatus'])
            ->name('assistance-types.update-status');
        Route::patch('signatories/{signatory}/status', [SignatoryController::class, 'updateStatus'])
            ->name('signatories.update-status');
    });

});
