<?php

use App\Enums\CariEntryKind;
use App\Http\Controllers\Admin\ApiAuditLogController;
use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Admin\BankController;
use App\Http\Controllers\Admin\CariEntryController;
use App\Http\Controllers\Admin\CariEntryLookupController;
use App\Http\Controllers\Admin\CashCategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DumpSiteController;
use App\Http\Controllers\Admin\EmployeeBonusController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\EmployeePaymentController;
use App\Http\Controllers\Admin\EmployeeSalaryController;
use App\Http\Controllers\Admin\ExampleController;
use App\Http\Controllers\Admin\FuelReceiptController;
use App\Http\Controllers\Admin\FuelReceiptLookupController;
use App\Http\Controllers\Admin\FuelStationController;
use App\Http\Controllers\Admin\FuelTankController;
use App\Http\Controllers\Admin\HaulController;
use App\Http\Controllers\Admin\HaulLookupController;
use App\Http\Controllers\Admin\HaulRouteController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\MachineWorkController;
use App\Http\Controllers\Admin\MachineWorkLookupController;
use App\Http\Controllers\Admin\PartyController;
use App\Http\Controllers\Admin\Permission\PermissionController;
use App\Http\Controllers\Admin\Reports\CashMovementReportController;
use App\Http\Controllers\Admin\Reports\CompanyBalanceReportController;
use App\Http\Controllers\Admin\Reports\CompanyLedgerReportController;
use App\Http\Controllers\Admin\Reports\DumpSitePaymentReportController;
use App\Http\Controllers\Admin\Reports\DumpSiteReportController;
use App\Http\Controllers\Admin\Reports\EmployeeAnnualReportController;
use App\Http\Controllers\Admin\Reports\EmployeeMonthlyReportController;
use App\Http\Controllers\Admin\Reports\FuelStationMonthlyReportController;
use App\Http\Controllers\Admin\Reports\FuelStationReportController;
use App\Http\Controllers\Admin\Reports\MonthlyVoucherReportController;
use App\Http\Controllers\Admin\Reports\OwnerBalanceReportController;
use App\Http\Controllers\Admin\Reports\OwnerMonthlyReportController;
use App\Http\Controllers\Admin\Reports\OwnerPeriodicReportController;
use App\Http\Controllers\Admin\Reports\SiteCariReportController;
use App\Http\Controllers\Admin\Reports\SiteExitReportController;
use App\Http\Controllers\Admin\Reports\SiteExpenseReportController;
use App\Http\Controllers\Admin\Reports\TicketControlReportController;
use App\Http\Controllers\Admin\Reports\VehicleBalanceReportController;
use App\Http\Controllers\Admin\Reports\VehicleCostOwnerSummaryController;
use App\Http\Controllers\Admin\Reports\VehicleCostReportController;
use App\Http\Controllers\Admin\Reports\VehicleMonthlyReportController;
use App\Http\Controllers\Admin\Reports\VehiclePeriodicReportController;
use App\Http\Controllers\Admin\Reports\VehicleSiteMonthlyReportController;
use App\Http\Controllers\Admin\Role\RoleController;
use App\Http\Controllers\Admin\RouteVehiclePriceController;
use App\Http\Controllers\Admin\ShipmentController;
use App\Http\Controllers\Admin\ShipmentLookupController;
use App\Http\Controllers\Admin\SiteController;
use App\Http\Controllers\Admin\User\UserController;
use App\Http\Controllers\Admin\VehicleClassController;
use App\Http\Controllers\Admin\VehicleController;
use App\Http\Controllers\Admin\VehicleOwnershipController;
use App\Http\Controllers\Admin\VehicleRateController;
use App\Http\Controllers\Admin\WebAuditLogController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])
        ->middleware('can:view-dashboard')
        ->name('dashboard');

    Route::middleware('can:view-web-audit-log')->group(function () {
        Route::get('web-audit-logs', [WebAuditLogController::class, 'index'])->name('web-audit-logs.index');
        Route::get('web-audit-logs/{auditLog}', [WebAuditLogController::class, 'show'])->name('web-audit-logs.show');
    });

    Route::middleware('can:view-api-audit-log')->group(function () {
        Route::get('api-audit-logs', [ApiAuditLogController::class, 'index'])->name('api-audit-logs.index');
        Route::get('api-audit-logs/{auditLog}', [ApiAuditLogController::class, 'show'])->name('api-audit-logs.show');
    });

    Route::middleware('can:view-example')->group(function () {
        Route::resource('examples', ExampleController::class)->except(['show']);
    });

    Route::middleware('can:view-cash-category')->group(function () {
        Route::resource('cash-categories', CashCategoryController::class);
    });

    Route::middleware('can:view-bank')->group(function () {
        Route::resource('banks', BankController::class);
    });

    Route::middleware('can:view-party')->group(function () {
        Route::get('locations/cities/{city}/districts', [LocationController::class, 'districts'])->name('locations.districts');
        Route::get('locations/districts/{district}/neighborhoods', [LocationController::class, 'neighborhoods'])->name('locations.neighborhoods');
        Route::get('persons', [PartyController::class, 'index'])->name('persons.index')->defaults('type', 'person');
        Route::get('companies', [PartyController::class, 'index'])->name('companies.index')->defaults('type', 'company');
        Route::resource('parties', PartyController::class)->except(['index']);
    });

    Route::middleware('can:view-cari-entry')->group(function () {
        Route::get('cari-entries/lookups/plates', [CariEntryLookupController::class, 'plate'])->name('cari-entries.lookups.plate');
        Route::get('cari-entries/lookups/parties/{party}', [CariEntryLookupController::class, 'party'])->name('cari-entries.lookups.party');
        Route::get('cari-entries', [CariEntryController::class, 'index'])->name('cari-entries.index');
        Route::get('cari-entries/create', [CariEntryController::class, 'create'])->name('cari-entries.create');
        Route::post('cari-entries', [CariEntryController::class, 'store'])->name('cari-entries.store');
        Route::get('cari-entries/{kind}/{entry}', [CariEntryController::class, 'show'])
            ->whereIn('kind', array_column(CariEntryKind::cases(), 'value'))
            ->name('cari-entries.show');
        Route::get('cari-entries/{kind}/{entry}/edit', [CariEntryController::class, 'edit'])->name('cari-entries.edit');
        Route::put('cari-entries/{kind}/{entry}', [CariEntryController::class, 'update'])->name('cari-entries.update');
        Route::delete('cari-entries/{kind}/{entry}', [CariEntryController::class, 'destroy'])->name('cari-entries.destroy');
    });

    Route::middleware('can:view-vehicle')->group(function () {
        Route::resource('vehicles', VehicleController::class);
        Route::post('vehicles/{vehicle}/ownerships/reassign', [VehicleOwnershipController::class, 'reassign'])->name('vehicles.ownerships.reassign');
        Route::resource('vehicles.ownerships', VehicleOwnershipController::class)->only(['store', 'destroy'])->scoped();
        Route::resource('vehicles.rates', VehicleRateController::class)->only(['store', 'destroy'])->scoped();
    });

    Route::middleware('can:view-vehicle-class')->group(function () {
        Route::resource('vehicle-classes', VehicleClassController::class);
    });

    Route::middleware('can:view-site')->group(function () {
        Route::resource('sites', SiteController::class);
    });

    Route::middleware('can:view-dump-site')->group(function () {
        Route::resource('dump-sites', DumpSiteController::class);
    });

    Route::middleware('can:view-haul-route')->group(function () {
        Route::resource('haul-routes', HaulRouteController::class);
        Route::resource('haul-routes.vehicle-prices', RouteVehiclePriceController::class)->only(['store', 'destroy'])->scoped();
    });

    Route::middleware('can:view-fuel-station')->group(function () {
        Route::resource('fuel-stations', FuelStationController::class);
        Route::post('fuel-stations/{fuelStation}/fills', [FuelTankController::class, 'storeFill'])->name('fuel-stations.fills.store');
        Route::delete('fuel-stations/{fuelStation}/fills/{fill}', [FuelTankController::class, 'destroyFill'])->scopeBindings()->name('fuel-stations.fills.destroy');
        Route::post('fuel-stations/{fuelStation}/counts', [FuelTankController::class, 'storeCount'])->name('fuel-stations.counts.store');
        Route::delete('fuel-stations/{fuelStation}/counts/{count}', [FuelTankController::class, 'destroyCount'])->scopeBindings()->name('fuel-stations.counts.destroy');
    });

    Route::middleware('can:view-employee')->group(function () {
        Route::get('employees/{employee}/balance', [EmployeeController::class, 'balance'])->name('employees.balance');
        Route::resource('employees', EmployeeController::class);
    });

    Route::middleware('can:view-employee-salary')->group(function () {
        Route::get('employee-salaries', [EmployeeSalaryController::class, 'index'])->name('employee-salaries.index');
        Route::post('employee-salaries', [EmployeeSalaryController::class, 'store'])->name('employee-salaries.store');
    });

    Route::middleware('can:view-employee-payment')->group(function () {
        Route::resource('employee-payments', EmployeePaymentController::class);
    });

    Route::middleware('can:view-employee-bonus')->group(function () {
        Route::resource('employee-bonuses', EmployeeBonusController::class);
    });

    Route::middleware('can:view-haul')->group(function () {
        Route::get('hauls/lookups/dump-sites', [HaulLookupController::class, 'dumpSites'])->name('hauls.lookups.dump-sites');
        Route::get('hauls/lookups/quote', [HaulLookupController::class, 'quote'])->name('hauls.lookups.quote');
        Route::get('hauls/lookups/vehicles', [HaulLookupController::class, 'vehicles'])->name('hauls.lookups.vehicles');
        Route::resource('hauls', HaulController::class);
    });

    Route::middleware('can:view-machine-work')->group(function () {
        Route::get('machine-works/lookups/machines', [MachineWorkLookupController::class, 'machines'])->name('machine-works.lookups.machines');
        Route::get('machine-works/lookups/rate', [MachineWorkLookupController::class, 'rate'])->name('machine-works.lookups.rate');
        Route::get('machine-works/lookups/operator', [MachineWorkLookupController::class, 'operator'])->name('machine-works.lookups.operator');
        Route::resource('machine-works', MachineWorkController::class);
    });

    Route::middleware('can:view-fuel-receipt')->group(function () {
        Route::get('fuel-receipts/lookups/vehicles', [FuelReceiptLookupController::class, 'vehicles'])->name('fuel-receipts.lookups.vehicles');
        Route::get('fuel-receipts/lookups/stations/{fuelStation}', [FuelReceiptLookupController::class, 'station'])->name('fuel-receipts.lookups.station');
        Route::resource('fuel-receipts', FuelReceiptController::class);
    });

    Route::middleware('can:view-shipment')->group(function () {
        Route::get('shipments/lookups/vehicles', [ShipmentLookupController::class, 'vehicles'])->name('shipments.lookups.vehicles');
        Route::get('shipments/lookups/addresses', [ShipmentLookupController::class, 'addresses'])->name('shipments.lookups.addresses');
        Route::resource('shipments', ShipmentController::class);
    });

    Route::middleware('can:view-report-monthly-vouchers')->group(function () {
        Route::get('reports/monthly-vouchers', [MonthlyVoucherReportController::class, 'index'])->name('reports.monthly-vouchers');
        Route::get('reports/monthly-vouchers/export', [MonthlyVoucherReportController::class, 'export'])->name('reports.monthly-vouchers.export');
    });
    Route::middleware('can:view-report-site-exits')->group(function () {
        Route::get('reports/site-exits', [SiteExitReportController::class, 'index'])->name('reports.site-exits');
        Route::get('reports/site-exits/export', [SiteExitReportController::class, 'export'])->name('reports.site-exits.export');
    });
    Route::middleware('can:view-report-dump-sites')->group(function () {
        Route::get('reports/dump-sites', [DumpSiteReportController::class, 'index'])->name('reports.dump-sites');
        Route::get('reports/dump-sites/export', [DumpSiteReportController::class, 'export'])->name('reports.dump-sites.export');
    });
    Route::middleware('can:view-report-dump-site-payments')->group(function () {
        Route::get('reports/dump-site-payments', [DumpSitePaymentReportController::class, 'index'])->name('reports.dump-site-payments');
        Route::get('reports/dump-site-payments/export', [DumpSitePaymentReportController::class, 'export'])->name('reports.dump-site-payments.export');
    });
    Route::middleware('can:view-report-fuel-stations')->group(function () {
        Route::get('reports/fuel-stations', [FuelStationReportController::class, 'index'])->name('reports.fuel-stations');
        Route::get('reports/fuel-stations/export', [FuelStationReportController::class, 'export'])->name('reports.fuel-stations.export');
    });
    Route::middleware('can:view-report-fuel-stations-monthly')->group(function () {
        Route::get('reports/fuel-stations-monthly', [FuelStationMonthlyReportController::class, 'index'])->name('reports.fuel-stations-monthly');
        Route::get('reports/fuel-stations-monthly/export', [FuelStationMonthlyReportController::class, 'export'])->name('reports.fuel-stations-monthly.export');
    });
    Route::middleware('can:view-report-ticket-control')->group(function () {
        Route::get('reports/ticket-control', [TicketControlReportController::class, 'index'])->name('reports.ticket-control');
        Route::get('reports/ticket-control/export', [TicketControlReportController::class, 'export'])->name('reports.ticket-control.export');
    });
    Route::middleware('can:view-report-vehicle-monthly')->group(function () {
        Route::get('reports/vehicle-monthly', [VehicleMonthlyReportController::class, 'index'])->name('reports.vehicle-monthly');
        Route::get('reports/vehicle-monthly/export', [VehicleMonthlyReportController::class, 'export'])->name('reports.vehicle-monthly.export');
    });
    Route::middleware('can:view-report-vehicle-periodic')->group(function () {
        Route::get('reports/vehicle-periodic', [VehiclePeriodicReportController::class, 'index'])->name('reports.vehicle-periodic');
        Route::get('reports/vehicle-periodic/export', [VehiclePeriodicReportController::class, 'export'])->name('reports.vehicle-periodic.export');
    });
    Route::middleware('can:view-report-vehicle-site-monthly')->group(function () {
        Route::get('reports/vehicle-site-monthly', [VehicleSiteMonthlyReportController::class, 'index'])->name('reports.vehicle-site-monthly');
        Route::get('reports/vehicle-site-monthly/export', [VehicleSiteMonthlyReportController::class, 'export'])->name('reports.vehicle-site-monthly.export');
    });
    Route::middleware('can:view-report-vehicle-balance')->group(function () {
        Route::get('reports/vehicle-balance', [VehicleBalanceReportController::class, 'index'])->name('reports.vehicle-balance');
        Route::get('reports/vehicle-balance/export', [VehicleBalanceReportController::class, 'export'])->name('reports.vehicle-balance.export');
    });
    Route::middleware('can:view-report-owner-monthly')->group(function () {
        Route::get('reports/owner-monthly', [OwnerMonthlyReportController::class, 'index'])->name('reports.owner-monthly');
        Route::get('reports/owner-monthly/export', [OwnerMonthlyReportController::class, 'export'])->name('reports.owner-monthly.export');
    });
    Route::middleware('can:view-report-owner-periodic')->group(function () {
        Route::get('reports/owner-periodic', [OwnerPeriodicReportController::class, 'index'])->name('reports.owner-periodic');
        Route::get('reports/owner-periodic/export', [OwnerPeriodicReportController::class, 'export'])->name('reports.owner-periodic.export');
    });
    Route::middleware('can:view-report-owner-balance')->group(function () {
        Route::get('reports/owner-balance', [OwnerBalanceReportController::class, 'index'])->name('reports.owner-balance');
        Route::get('reports/owner-balance/export', [OwnerBalanceReportController::class, 'export'])->name('reports.owner-balance.export');
    });
    Route::middleware('can:view-report-vehicle-cost')->group(function () {
        Route::get('reports/vehicle-cost', [VehicleCostReportController::class, 'index'])->name('reports.vehicle-cost');
        Route::get('reports/vehicle-cost/export', [VehicleCostReportController::class, 'export'])->name('reports.vehicle-cost.export');
    });
    Route::middleware('can:view-report-vehicle-cost-owners')->group(function () {
        Route::get('reports/vehicle-cost/owners', [VehicleCostOwnerSummaryController::class, 'index'])->name('reports.vehicle-cost.owners');
        Route::get('reports/vehicle-cost/owners/export', [VehicleCostOwnerSummaryController::class, 'export'])->name('reports.vehicle-cost.owners.export');
    });
    Route::middleware('can:view-report-company-balance')->group(function () {
        Route::get('reports/company-balance', [CompanyBalanceReportController::class, 'index'])->name('reports.company-balance');
        Route::get('reports/company-balance/export', [CompanyBalanceReportController::class, 'export'])->name('reports.company-balance.export');
    });
    Route::middleware('can:view-report-company-ledger')->group(function () {
        Route::get('reports/company-ledger', [CompanyLedgerReportController::class, 'index'])->name('reports.company-ledger');
        Route::get('reports/company-ledger/export', [CompanyLedgerReportController::class, 'export'])->name('reports.company-ledger.export');
    });
    Route::middleware('can:view-report-site-cari')->group(function () {
        Route::get('reports/site-cari', [SiteCariReportController::class, 'index'])->name('reports.site-cari');
        Route::get('reports/site-cari/export', [SiteCariReportController::class, 'export'])->name('reports.site-cari.export');
    });
    Route::middleware('can:view-report-site-expense')->group(function () {
        Route::get('reports/site-expense', [SiteExpenseReportController::class, 'index'])->name('reports.site-expense');
        Route::get('reports/site-expense/export', [SiteExpenseReportController::class, 'export'])->name('reports.site-expense.export');
    });
    Route::middleware('can:view-report-cash-movements')->group(function () {
        Route::get('reports/cash-movements', [CashMovementReportController::class, 'index'])->name('reports.cash-movements');
        Route::get('reports/cash-movements/export', [CashMovementReportController::class, 'export'])->name('reports.cash-movements.export');
    });
    Route::middleware('can:view-report-employee-monthly')->group(function () {
        Route::get('reports/employee-monthly', [EmployeeMonthlyReportController::class, 'index'])->name('reports.employee-monthly');
        Route::get('reports/employee-monthly/export', [EmployeeMonthlyReportController::class, 'export'])->name('reports.employee-monthly.export');
    });
    Route::middleware('can:view-report-employee-annual')->group(function () {
        Route::get('reports/employee-annual', [EmployeeAnnualReportController::class, 'index'])->name('reports.employee-annual');
        Route::get('reports/employee-annual/export', [EmployeeAnnualReportController::class, 'export'])->name('reports.employee-annual.export');
    });

    Route::middleware('can:view-users')->group(function () {
        Route::resource('users', UserController::class);
    });

    Route::middleware('can:view-role')->group(function () {
        Route::resource('roles', RoleController::class);
    });

    Route::middleware('can:view-permission')->group(function () {
        Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
    });
});
