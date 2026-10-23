<?php

namespace Tests\Feature;

use App\Models\Ministry;
use App\Models\Organisation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommerceMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketplace_aggregates_published_ministry_products(): void
    {
        $organisation = Organisation::create([
            'name' => 'Alpha Ministries',
            'slug' => 'alpha',
            'status' => 'active',
            'modules' => Organisation::DEFAULT_MODULES,
        ]);

        Ministry::create([
            'organisation_id' => $organisation->id,
            'entity' => 'alpha',
            'slug' => 'coffee',
            'name' => 'Coffee Ministry',
        ]);

        $this->createAimeosProductTables();

        DB::table('mshop_locale_site')->insert(['id' => 1, 'siteid' => 'alpha-coffee']);
        DB::table('mshop_product')->insert([
            'id' => 10,
            'siteid' => 'alpha-coffee',
            'code' => 'coffee-beans',
            'label' => 'Coffee Beans',
            'url' => '',
            'rating' => 0,
            'ratings' => 0,
            'instock' => 1,
            'status' => 1,
            'start' => null,
            'end' => null,
            'mtime' => now(),
        ]);
        DB::table('mshop_text')->insert(['id' => 20, 'siteid' => 'alpha-coffee', 'type' => 'name', 'content' => 'Voca Coffee Beans', 'status' => 1]);
        DB::table('mshop_product_list')->insert(['siteid' => 'alpha-coffee', 'parentid' => 10, 'domain' => 'text', 'type' => 'default', 'refid' => '20', 'status' => 1]);
        DB::table('mshop_price')->insert(['id' => 30, 'siteid' => 'alpha-coffee', 'value' => 95, 'currencyid' => 'ZAR', 'status' => 1]);
        DB::table('mshop_product_list')->insert(['siteid' => 'alpha-coffee', 'parentid' => 10, 'domain' => 'price', 'type' => 'default', 'refid' => '30', 'status' => 1]);

        $this->getJson('http://alpha.app.voca.co.za/api/commerce/marketplace')
            ->assertOk()
            ->assertJsonPath('products.0.title', 'Voca Coffee Beans')
            ->assertJsonPath('products.0.ministry.name', 'Coffee Ministry')
            ->assertJsonPath('products.0.price.currency', 'ZAR')
            ->assertJsonPath('status.marketplace_extension', false);
    }

    private function createAimeosProductTables(): void
    {
        Schema::create('mshop_locale_site', function (Blueprint $table) {
            $table->id();
            $table->string('siteid');
        });

        Schema::create('mshop_product', function (Blueprint $table) {
            $table->id();
            $table->string('siteid');
            $table->string('code');
            $table->string('label');
            $table->string('url');
            $table->decimal('rating')->default(0);
            $table->integer('ratings')->default(0);
            $table->smallInteger('instock')->default(0);
            $table->smallInteger('status')->default(1);
            $table->dateTime('start')->nullable();
            $table->dateTime('end')->nullable();
            $table->dateTime('mtime')->nullable();
        });

        Schema::create('mshop_product_list', function (Blueprint $table) {
            $table->id();
            $table->string('siteid');
            $table->integer('parentid');
            $table->string('domain');
            $table->string('type');
            $table->string('refid');
            $table->smallInteger('status')->default(1);
        });

        Schema::create('mshop_text', function (Blueprint $table) {
            $table->id();
            $table->string('siteid');
            $table->string('type');
            $table->text('content');
            $table->smallInteger('status')->default(1);
        });

        Schema::create('mshop_price', function (Blueprint $table) {
            $table->id();
            $table->string('siteid');
            $table->decimal('value', 12, 2)->nullable();
            $table->string('currencyid', 3);
            $table->smallInteger('status')->default(1);
        });

        Schema::create('mshop_media', function (Blueprint $table) {
            $table->id();
            $table->string('siteid');
            $table->text('preview')->nullable();
            $table->string('link')->nullable();
            $table->smallInteger('status')->default(1);
        });
    }
}
