<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Leads are the people looking to buy or rent; deals follow each lead through the stages of
     * the account's funnel, with their visits and proposals.
     */
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->index()->constrained();
            $table->foreignId('branch_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone', 11)->nullable();
            $table->string('email')->nullable();
            $table->string('source')->nullable();
            $table->string('interest')->default('buy');
            $table->string('property_type')->nullable();
            $table->decimal('max_price', 12, 2)->nullable();
            $table->string('neighborhoods')->nullable();
            $table->string('payment_plan')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('deal_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->index()->constrained();
            $table->string('name');
            $table->string('outcome')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->index()->constrained();
            $table->foreignId('lead_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignId('deal_stage_id')->index()->constrained();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('sale');
            $table->decimal('value', 12, 2)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->string('lost_reason')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('deal_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->index()->constrained();
            $table->foreignId('deal_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('scheduled_at');
            $table->string('status')->default('scheduled');
            $table->text('feedback')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'scheduled_at']);
        });

        Schema::create('deal_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->index()->constrained();
            $table->foreignId('deal_id')->index()->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('payment_plan')->nullable();
            $table->text('conditions')->nullable();
            $table->string('status')->default('pending');
            $table->date('proposed_on');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deal_proposals');
        Schema::dropIfExists('deal_visits');
        Schema::dropIfExists('deals');
        Schema::dropIfExists('deal_stages');
        Schema::dropIfExists('leads');
    }
};
