<?php

use App\Models\Pricing\VatRate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The article gets its VAT rate back (HU-007 / HU-063). The column stays nullable: "required
     * for active articles" is enforced by the article Form Requests and by AddArticleToSale, since
     * a CHECK would mean rebuilding `articles` and every foreign key that points to it.
     *
     * Existing articles get the general 21% rate, created if the catalog has none yet.
     */
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->foreignId('vat_rate_id')->nullable()->after('unit_of_measure_id')->constrained('vat_rates')->restrictOnDelete();
        });

        if (! DB::table('articles')->exists()) {
            return;
        }

        $generalRateId = DB::table('vat_rates')->where('percentage', 21)->value('id')
            ?? DB::table('vat_rates')->insertGetId([
                'description' => 'General (21%)',
                'description_normalized' => VatRate::normalizeUniqueValue('General (21%)'),
                'percentage' => 21,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        DB::table('articles')->whereNull('vat_rate_id')->update(['vat_rate_id' => $generalRateId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vat_rate_id');
        });
    }
};
