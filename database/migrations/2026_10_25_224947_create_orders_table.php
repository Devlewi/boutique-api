<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
public function up()
{
    Schema::create('orders', function (Blueprint $table) {
        $table->id();

        $table->foreignId('user_id')
            ->constrained('users')
            ->cascadeOnDelete();

        $table->string('status')->default('pending');
        $table->unsignedBigInteger('total_cents')->default(0);

        $table->string('idempotency_key', 100)->nullable();

        $table->timestamps();

        $table->unique(
            ['user_id', 'idempotency_key'],
            'orders_user_id_idempotency_key_unique'
        );
    });
}


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('orders');
    }
}
