<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateImagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('images', function (Blueprint $table) {
            $table->timestamps();
            $table->integer('frame_num');
            $table->string('file_path');
            $table->float('yaw');
            $table->float('pitch');
            $table->float('roll');
            $table->json('data_points');
            $table->integer('video_id')->unsigned();
            $table->foreign('video_id')->references('id')->on('original_videos');
        });
        DB::statement('ALTER TABLE images ADD of_left_pupil POINT' );
        DB::statement('ALTER TABLE images ADD of_right_pupil POINT' );        
        DB::statement('ALTER TABLE images ADD ft_left_pupil POINT' );
        DB::statement('ALTER TABLE images ADD ft_right_pupil POINT' );
        DB::statement('ALTER TABLE images ADD PRIMARY KEY (  video_id ,  frame_num )');        
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('images');
    }
}
