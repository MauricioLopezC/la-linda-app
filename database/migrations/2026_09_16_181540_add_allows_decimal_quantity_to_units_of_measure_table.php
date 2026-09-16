<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('units_of_measure', function (Blueprint $table) {
            $table->boolean('allows_decimal_quantity')->default(false)->after('abbreviation_normalized');
        });

        // Backfill with the heuristic this column replaces (name/abbreviation matching in
        // UnitOfMeasure::allowsDecimals()), so behavior for existing rows doesn't change at
        // cutover. From here on the value is explicit and admin-editable, not inferred.
        $discreteAbbreviations = ['u', 'un', 'und', 'pk', 'bto', 'doc'];
        $discreteNames = ['unidad', 'pack', 'bulto', 'docena'];

        DB::table('units_of_measure')->select(['id', 'name_normalized', 'abbreviation_normalized'])
            ->orderBy('id')
            ->each(function (object $unit) use ($discreteAbbreviations, $discreteNames): void {
                $isDiscrete = in_array($unit->abbreviation_normalized, $discreteAbbreviations, true)
                    || in_array($unit->name_normalized, $discreteNames, true);

                DB::table('units_of_measure')
                    ->where('id', $unit->id)
                    ->update(['allows_decimal_quantity' => ! $isDiscrete]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units_of_measure', function (Blueprint $table) {
            $table->dropColumn('allows_decimal_quantity');
        });
    }
};
