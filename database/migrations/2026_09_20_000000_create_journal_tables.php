<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Jgawlik\LaravelJournal\JournalServiceProvider;

return new class extends Migration
{
    public function up(): void
    {
        $userModel = JournalServiceProvider::userModel();
        $user = new $userModel;

        Schema::create('journal_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('journal_tags', function (Blueprint $table) use ($userModel, $user) {
            $table->id();
            $table->foreignIdFor($userModel, 'user_id')->constrained($user->getTable(), $user->getKeyName())->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['user_id', 'slug']);
        });

        Schema::create('journal_posts', function (Blueprint $table) use ($userModel, $user) {
            $table->id();

            $table->foreignIdFor($userModel, 'user_id')->constrained($user->getTable(), $user->getKeyName())->cascadeOnDelete();

            $table->string('title');
            $table->string('slug');
            $table->longText('content');
            $table->string('cover_image', 2048)->nullable();

            $table->foreignId('category_id')
                ->nullable()
                ->constrained('journal_categories')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['user_id', 'slug']);
            $table->index(['user_id', 'created_at']);
            $table->index(['category_id', 'user_id']);
        });

        Schema::create('journal_post_tag', function (Blueprint $table) {
            $table->foreignId('journal_post_id')->constrained('journal_posts')->cascadeOnDelete();
            $table->foreignId('journal_tag_id')->constrained('journal_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['journal_post_id', 'journal_tag_id']);
            $table->index('journal_tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_post_tag');
        Schema::dropIfExists('journal_posts');
        Schema::dropIfExists('journal_tags');
        Schema::dropIfExists('journal_categories');
    }
};
