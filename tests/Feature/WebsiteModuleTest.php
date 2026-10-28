<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Models\Website\Slide;
use App\Services\Website\SiteSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Media › Website: pictures, videos and texts of the homepage, dry-hire and sales pages. */
class WebsiteModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
        SiteSettings::flush();
        $this->editor = User::factory()->create(['role_id' => Role::where('slug', 'sales')->value('id'), 'locale' => 'en']);
        $this->editor->extraRights()->create(['right' => 'website.edit']);
    }

    private function add(string $place, string $link = 'page', ?string $value = 'dryhire', array $extra = [], ?UploadedFile $file = null)
    {
        return $this->actingAs($this->editor->fresh(), 'web')->post(route('website.slides.store'), array_merge([
            'place' => $place, 'file' => $file ?? UploadedFile::fake()->image('hero.jpg', 1800, 900), 'link_type' => $link, 'link_value' => $value, 'active' => 1,
        ], $extra));
    }

    public function test_one_picture_is_still_more_become_a_slideshow_with_their_own_links(): void
    {
        // Nothing set: the built-in picture.
        $this->get('/site')->assertOk()->assertSee('images/site/hero.jpg', false)->assertDontSee('wsl-s', false);

        $this->add('home_hero')->assertSessionHasNoErrors()->assertRedirect();
        $s1 = Slide::firstOrFail();
        Storage::disk('local')->assertExists($s1->path);
        $page = $this->get('/site')->assertOk();
        $page->assertSee('class="wsl-s on"', false)->assertSee(route('site.dryhire'), false)->assertDontSee('data-wslides', false)->assertDontSee('images/site/hero.jpg', false);

        // A video that goes to a web address: now a slideshow.
        $this->add('home_hero', 'url', 'https://cluk.live/demo', [], UploadedFile::fake()->create('clip.mp4', 2000, 'video/mp4'))->assertSessionHasNoErrors();
        $video = Slide::where('kind', 'video')->firstOrFail();
        $this->assertStringEndsWith('.mp4', $video->path);
        $page = $this->get('/site')->assertOk()->assertSee('data-wslides', false)->assertSee('<video', false)->assertSee('https://cluk.live/demo', false)->assertSee('js/site-slides.js', false);

        // The media files are served; an unknown name is not.
        $this->get(SiteSettings::mediaUrl($s1->path))->assertOk();
        $this->get('/site/media/'.str_repeat('a', 32).'.jpg')->assertNotFound();

        // Switched off or out of its dates: gone from the website.
        $this->actingAs($this->editor->fresh(), 'web')->post(route('website.slides.update', $video), ['link_type' => 'url', 'link_value' => 'https://cluk.live/demo', 'active' => 0])->assertSessionHasNoErrors();
        $this->get('/site')->assertDontSee('data-wslides', false);
        $this->actingAs($this->editor->fresh(), 'web')->post(route('website.slides.update', $video), ['link_type' => 'none', 'active' => 1, 'starts_on' => today()->addDays(3)->toDateString()]);
        $this->get('/site')->assertDontSee('data-wslides', false);

        // Order and removal.
        $this->actingAs($this->editor->fresh(), 'web')->post(route('website.slides.move', [$video, 'up']))->assertRedirect();
        $this->assertSame([$video->id, $s1->id], Slide::orderBy('position')->pluck('id')->all());
        $this->actingAs($this->editor->fresh(), 'web')->delete(route('website.slides.destroy', $s1))->assertRedirect();
        Storage::disk('local')->assertMissing($s1->path);
        $this->assertSame(1, Slide::count());
        $this->assertSame(['website.slide_added', 'website.slide_added', 'website.slide_changed', 'website.slide_changed', 'website.slide_removed'],
            AuditLog::where('action', 'like', 'website.%')->orderBy('id')->pluck('action')->all());
    }

    public function test_a_slide_can_have_its_own_phone_picture(): void
    {
        $this->add('sales_slider', 'page', 'sales')->assertSessionHasNoErrors();
        $slide = Slide::firstOrFail();
        $this->actingAs($this->editor->fresh(), 'web')->get(route('website.index', ['tab' => 'sales']))->assertOk()->assertSee('Picture for phones')->assertSee('1200 × 1290 px');
        $this->actingAs($this->editor->fresh(), 'web')->post(route('website.slides.update', $slide), ['link_type' => 'page', 'link_value' => 'sales', 'active' => 1,
            'mobile' => UploadedFile::fake()->image('phone.jpg', 1200, 1290)])->assertSessionHasNoErrors();
        $this->assertNotNull($slide->fresh()->mobile_path);
        $this->get(route('site.sales'))->assertOk()->assertSee('--bg-phone', false);

        $this->add('home_hero', 'page', 'sales', [], UploadedFile::fake()->image('h.jpg', 2400, 2000))->assertSessionHasNoErrors();
        $hero = Slide::where('place', 'home_hero')->firstOrFail();
        $this->actingAs($this->editor->fresh(), 'web')->post(route('website.slides.update', $hero), ['link_type' => 'page', 'link_value' => 'sales', 'active' => 1,
            'mobile' => UploadedFile::fake()->image('p.jpg', 1200, 920)])->assertSessionHasNoErrors();
        $this->get(route('site.home'))->assertOk()->assertSee('<source media="(max-width: 600px)"', false);

        $this->actingAs($this->editor->fresh(), 'web')->post(route('website.slides.update', $slide), ['link_type' => 'page', 'link_value' => 'sales', 'active' => 1, 'remove_mobile' => 1])->assertSessionHasNoErrors();
        $this->assertNull($slide->fresh()->mobile_path);
    }

    public function test_links_must_lead_somewhere(): void
    {
        $this->add('sales_slider', 'sales_item', 'NOPE-123')->assertSessionHasErrors('link_value');
        $this->add('sales_slider', 'url', 'javascript:alert(1)')->assertSessionHasErrors('link_value');
        $this->add('sales_slider', 'page', 'nowhere')->assertSessionHasErrors('link_value');
        $this->assertSame(0, Slide::count());
        $this->add('sales_slider', 'none', null)->assertSessionHasNoErrors();
    }

    public function test_sales_slideshow_shows_own_texts_and_keeps_built_in_slides_until_set(): void
    {
        $this->get('/site/sales')->assertOk()->assertSee('Our autumn selection');
        $this->add('sales_slider', 'page', 'brands', ['texts' => ['en' => ['title' => 'Spring deals', 'cta' => 'Show me'], 'nl' => ['title' => 'Lentedeals']]])->assertSessionHasNoErrors();
        $this->get('/site/sales')->assertOk()->assertSee('Spring deals')->assertSee('Show me')->assertSee(route('site.sales.brands'), false)->assertDontSee('Our autumn selection')->assertDontSee('id="dots"', false);
        $this->add('sales_slider', 'page', 'sales_new')->assertSessionHasNoErrors();
        $this->get('/site/sales')->assertSee('id="dots"', false);
    }

    public function test_texts_tiles_and_general_settings_with_fallbacks(): void
    {
        $this->actingAs($this->editor->fresh(), 'web')->post(route('website.texts', 'home'), ['t' => ['home__hero_title' => ['en' => 'Light it up', 'nl' => '']]])->assertSessionHasNoErrors();
        $this->actingAs($this->editor->fresh(), 'web')->post(route('website.tile', 'factory'), ['text' => ['en' => 'Our venue in Zulte'], 'cta' => ['en' => 'Visit'], 'image' => UploadedFile::fake()->image('f.jpg', 1200, 800)])->assertSessionHasNoErrors();
        $this->actingAs($this->editor->fresh(), 'web')->post(route('website.general'), ['tab' => 'general', 'slide_seconds' => 9, 'instagram' => 'https://instagram.com/clukdemo'])->assertSessionHasNoErrors();
        $this->actingAs($this->editor->fresh(), 'web')->post(route('website.general'), ['tab' => 'dryhire', 'popular' => 'blinder, haze , ,truss'])->assertSessionHasNoErrors();

        $this->get('/site')->assertSee('Light it up')->assertSee('Our venue in Zulte')->assertSee('Visit')->assertSee('https://instagram.com/clukdemo', false)->assertSee('/site/media/', false);
        $this->assertSame(['blinder', 'haze', 'truss'], SiteSettings::popular());
        $this->get('/site/dry-hire')->assertOk()->assertSee('data-q="haze"', false);
        // Dutch visitor: no Dutch text yet, so the English one.
        $this->assertSame('Light it up', SiteSettings::text('home.hero_title', 'nl'));
        // Untouched texts keep the built-in one.
        $this->assertSame(__('site.home.news_title', [], 'fr'), SiteSettings::text('home.news_title', 'fr'));
        $this->assertSame(4, AuditLog::where('action', 'like', 'website.%')->count());
    }

    public function test_only_people_with_the_right_can_change(): void
    {
        $other = User::factory()->create(['role_id' => Role::where('slug', 'sales')->value('id'), 'locale' => 'en']);
        $this->actingAs($other, 'web')->get(route('website.index', ['tab' => 'sales']))->assertOk()->assertSee('You can look, but not change');
        $this->actingAs($other, 'web')->post(route('website.slides.store'), ['place' => 'home_hero', 'file' => UploadedFile::fake()->image('x.jpg'), 'link_type' => 'none'])->assertForbidden();
        $this->actingAs($other, 'web')->post(route('website.texts', 'home'), ['t' => []])->assertForbidden();
        foreach (['home', 'dryhire', 'sales', 'general'] as $tab) {
            $this->actingAs($this->editor->fresh(), 'web')->get(route('website.index', ['tab' => $tab]))->assertOk()->assertDontSee('You can look');
        }
        foreach (['nl' => 'Homepage', 'fr' => 'Accueil', 'es' => 'Inicio'] as $loc => $t) {
            $this->editor->update(['locale' => $loc]);
            $this->actingAs($this->editor->fresh(), 'web')->get(route('website.index'))->assertOk()->assertSee($t);
        }
    }

    public function test_ideal_formats_template_and_a_warning_for_pictures_that_will_not_fit(): void
    {
        $this->actingAs($this->editor->fresh(), 'web')->get(route('website.index', ['tab' => 'sales']))->assertOk()
            ->assertSee('2400 × 880 px (30:11)')->assertSee('the middle 810 × 870 px')->assertSee(route('website.template', 'sales_slider'), false);
        $svg = $this->actingAs($this->editor->fresh(), 'web')->get(route('website.template', 'home_hero'))->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('width="2400" height="2000"', $svg->getContent());
        $this->assertStringContainsString('Always visible 1480 × 1290', $svg->getContent());
        $this->assertNotFalse(simplexml_load_string($svg->getContent()));

        // A small, square picture: added, with a warning.
        $this->add('home_hero', 'none', null, [], UploadedFile::fake()->image('small.jpg', 800, 800))->assertSessionHas('status', fn ($m) => str_contains($m, 'the ideal is 2400 × 2000 px'));
        // The right size: no warning; the previews per screen are there.
        $this->add('home_hero', 'none', null, [], UploadedFile::fake()->image('ok.jpg', 2400, 2000))->assertSessionHas('status', 'Added to the website.');
        $this->actingAs($this->editor->fresh(), 'web')->get(route('website.index'))->assertSee('ws-crops', false)->assertSee('Wide screen');
    }
}
