<?php

namespace App\Providers;

use App\Modules\Core\Models\User;
use App\Modules\Core\Policies\UserPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMorphMap();
        $this->configureQueryLogging();
        // $this->registerObservers();
        $this->registerGates();
    }

    /**
     * Register authorization gates for non-model permissions.
     */
    protected function registerGates(): void
    {
        // Register model policies
        Gate::policy(User::class, UserPolicy::class);

        // Super-admin bypass: users with the spatie 'admin' role can do anything.
        Gate::before(function ($user) {
            if ($user->hasRole('admin', 'api')) {
                return true;
            }
        });

        // Gate for role/permission management
        Gate::define('manage-roles', fn ($user) => $user->hasPermissionTo('manage-roles', 'api'));

        // Gate for report access
        Gate::define('view-reports', fn ($user) => $user->hasPermissionTo('reports.view', 'api'));
    }

    /**
     * Register model observers for workflow side effects.
     */
    // protected function registerObservers(): void
    // {
    //     \App\Modules\Research\Models\Experiment::observe(\App\Observers\ExperimentObserver::class);
    //     \App\Modules\Business\Models\Contract::observe(\App\Observers\ContractObserver::class);
    //     \App\Modules\Business\Models\Payment::observe(\App\Observers\PaymentObserver::class);
    //     \App\Modules\Business\Models\ContractMilestone::observe(\App\Observers\ContractMilestoneObserver::class);
    //     \App\Modules\Inventory\Models\BorrowRecord::observe(\App\Observers\BorrowRecordObserver::class);
    //     \App\Modules\Research\Models\LabNotebook::observe(\App\Observers\LabNotebookObserver::class);
    //     \App\Modules\Inventory\Models\ChemicalBatch::observe(\App\Observers\ChemicalBatchObserver::class);
    // }

    /**
     * Register the polymorphic morph map so DB stores short aliases
     * instead of fully-qualified class names. Prevents breakage on refactors.
     */
    protected function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'user' => \App\Modules\Core\Models\User::class,
            'plant_species' => \App\Modules\Inventory\Models\PlantSpecies::class,
            'plant_variety' => \App\Modules\Inventory\Models\PlantVariety::class,
            'plant_sample' => \App\Modules\Inventory\Models\PlantSample::class,
            'plant_stock' => \App\Modules\Inventory\Models\PlantStock::class,
            'chemical' => \App\Modules\Inventory\Models\Chemical::class,
            'chemical_batch' => \App\Modules\Inventory\Models\ChemicalBatch::class,
            'chemical_usage_log' => \App\Modules\Inventory\Models\ChemicalUsageLog::class,
            'equipment' => \App\Modules\Inventory\Models\Equipment::class,
            'borrow_record' => \App\Modules\Inventory\Models\BorrowRecord::class,
            'maintenance_record' => \App\Modules\Inventory\Models\MaintenanceRecord::class,
            'transaction' => \App\Modules\Inventory\Models\Transaction::class,
            'achievement' => \App\Modules\Inventory\Models\Achievement::class,
            'user_document' => \App\Modules\Inventory\Models\UserDocument::class,
            // // Research module
            // 'experiment' => \App\Modules\Research\Models\Experiment::class,
            // 'growth_log' => \App\Modules\Research\Models\GrowthLog::class,
            // 'protocol' => \App\Modules\Research\Models\Protocol::class,
            // 'protocol_step' => \App\Modules\Research\Models\ProtocolStep::class,
            // 'lab_notebook' => \App\Modules\Research\Models\LabNotebook::class,
            // 'experiment_material' => \App\Modules\Research\Models\ExperimentMaterial::class,
            // 'tag' => \App\Modules\Core\Models\Tag::class,
            // // Business module
            // 'client' => \App\Modules\Business\Models\Client::class,
            // 'contract' => \App\Modules\Business\Models\Contract::class,
            // 'contract_milestone' => \App\Modules\Business\Models\ContractMilestone::class,
            // 'payment' => \App\Modules\Business\Models\Payment::class,
            // 'production_forecast' => \App\Modules\Business\Models\ProductionForecast::class,
            // 'lab_service' => \App\Modules\Business\Models\LabService::class,
            // 'location_history' => \App\Modules\Inventory\Models\LocationHistory::class,
        ]);
    }

    /**
     * Log slow database queries in development.
     * Threshold: 500ms (adjust via DB_SLOW_QUERY_MS env var).
     */
    protected function configureQueryLogging(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $threshold = (int) env('DB_SLOW_QUERY_MS', 500);

        DB::listen(function ($query) use ($threshold) {
            if ($query->time >= $threshold) {
                logger()->warning('Slow query detected', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time_ms' => $query->time,
                ]);
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Prevent N+1 queries in development; log them silently in production.
        Model::preventLazyLoading(! app()->isProduction());

        // Prevent silently discarding attributes not in $fillable.
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
