<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateItemsTable extends Migration
{
    public function up()
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name'); // example attribute
            $table->text('description')->nullable(); // another example attribute
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations by dropping the 'items' table.
     */
    public function down()
    {
        Schema::dropIfExists('items');
    }
}
