<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add transaction_reference_hash to enforce uniqueness on payment proofs.
 *
 * The existing transaction_reference uses Laravel's encrypted cast which
 * employs a random IV per encryption. This makes DB-level and app-level
 * uniqueness checks ineffective (same plaintext -> different ciphertext).
 *
 * Solution: store a SHA-256 hash of the plaintext reference in a separate
 * column with a unique index. The hash is deterministic and collision-resistant.
 * The original encrypted column is preserved for display purposes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->string('transaction_reference_hash', 64)->nullable()->after('transaction_reference');
        });

        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->unique('transaction_reference_hash', 'idx_payment_proofs_ref_hash_unique');
        });

        $this->backfillHashes();
    }

    public function down(): void
    {
        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->dropUnique('idx_payment_proofs_ref_hash_unique');
            $table->dropColumn('transaction_reference_hash');
        });
    }

    private function backfillHashes(): void
    {
        $batchSize = 500;
        $lastId = 0;

        while (true) {
            $rows = DB::table('payment_proofs')
                ->whereNull('transaction_reference_hash')
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit($batchSize)
                ->pluck('transaction_reference', 'id');

            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $id => $encryptedRef) {
                $plaintext = $this->decryptSafely($encryptedRef);
                $hash = $plaintext !== null ? hash('sha256', $plaintext) : null;

                DB::table('payment_proofs')
                    ->where('id', $id)
                    ->update(['transaction_reference_hash' => $hash]);

                $lastId = $id;
            }
        }
    }

    private function decryptSafely(?string $encrypted): ?string
    {
        if ($encrypted === null) {
            return null;
        }

        try {
            return decrypt($encrypted);
        } catch (Throwable $e) {
            // If decryption fails (e.g. already plaintext or corrupted), treat as-is
            return $encrypted;
        }
    }
};
