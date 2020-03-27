<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTypeToPostModel extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('blog_etc_posts', function (Blueprint $table) {
            $table->boolean('is_featured')->after('is_published')->nullable()->default(0);
            $table->string('type')->after('is_featured')->length(10)->default('article');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('blog_etc_posts', function (Blueprint $table) {
            $table->dropColumn('is_featured');
            $table->dropColumn('type');
        });
    }
}
