<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSortOrderToCategories extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('blog_etc_categories', function (Blueprint $table) {
            $table->smallInteger('sort_order')->unsigned();
            $table->index('sort_order', 'categories_sort_order_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('blog_etc_categories', function (Blueprint $table) {
            $table->dropIndex('categories_sort_order_index');
            $table->dropColumn('sort_order');
        });
    }
}
