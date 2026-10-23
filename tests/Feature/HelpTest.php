<?php

namespace Tests\Feature;

use App\Models\Help\Article;
use App\Models\Help\HelpFile;
use App\Models\Help\Question;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Confirmation;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HelpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Notification::fake();
        Storage::fake('local');
    }

    private function as(string $role, string $locale = 'en'): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'locale' => $locale]);
    }

    public function test_search_finds_answers_in_plain_words_and_any_language(): void
    {
        $w = $this->as('warehouse');
        $this->assertGreaterThan(15, Article::count());
        $this->actingAs($w)->get('/help')->assertOk()->assertSee('How can we help?')->assertSee('Printing QR labels on the Dymo');

        // A problem in plain words, an English question and a Dutch question all find the label article first.
        foreach (['the label printer prints nothing', 'how do I print a sticker', 'etiket printen lukt niet'] as $q) {
            $r = $this->actingAs($w)->get('/help?q='.urlencode($q))->assertOk();
            $html = $r->getContent();
            $first = strpos($html, 'class="help-item"');
            $this->assertNotFalse($first, $q);
            $this->assertStringContainsString('printing-qr-labels', substr($html, $first, 300), $q);
        }
        $this->actingAs($w)->get('/help?q=xyzzy+qwerty')->assertOk()->assertSee('Nothing found for');

        $this->actingAs($w)->get('/help/printing-qr-labels')->assertOk()->assertSee('2112290')->assertSee('<ol>', false);
        $this->assertSame(1, Article::where('slug', 'printing-qr-labels')->value('views'));
        $nl = $this->as('warehouse', 'nl');
        $this->actingAs($nl)->get('/help/printing-qr-labels')->assertOk()->assertSee('QR-labels printen op de Dymo');
        foreach (['fr' => 'Comment pouvons-nous aider', 'es' => 'Cómo podemos ayudarte'] as $loc => $t) {
            $this->actingAs($this->as('sales', $loc))->get('/help')->assertOk()->assertSee($t, false);
        }
    }

    public function test_question_goes_to_the_admin_and_the_answer_comes_back(): void
    {
        $admin = $this->as('admin');
        $w = $this->as('warehouse');
        $this->actingAs($w)->post('/help/ask', ['question' => 'How do I scan a case with a broken QR?', 'page' => '/w'])->assertRedirect()->assertSessionHas('status');
        $q = Question::firstOrFail();
        Notification::assertSentTo($admin, Confirmation::class);

        $this->actingAs($w)->get('/help/manage')->assertForbidden();
        $this->actingAs($admin)->get('/help')->assertOk()->assertSee('Import templates')->assertSee('Manage help');
        $this->actingAs($admin)->get('/help/manage')->assertOk()->assertSee('broken QR');
        $this->actingAs($admin)->post('/help/questions/'.$q->id.'/answer', ['answer' => 'Type the code under the QR in the search field.'])->assertRedirect();
        $this->assertSame('answered', $q->fresh()->status);
        Notification::assertSentTo($w, Confirmation::class);
        $this->actingAs($w)->get('/help')->assertSee('See the answer')->assertSee('Type the code under the QR');

        // The answer becomes an article.
        $this->actingAs($admin)->get('/help/new?from='.$q->id)->assertOk()->assertSee('broken QR');
        $this->actingAs($admin)->post('/help/articles', ['from_question' => $q->id, 'topic' => 'warehouse', 'active' => 1,
            'title' => ['en' => 'A QR label is broken'], 'body' => ['en' => "1. Type the code.\n2. Print a new label."]])->assertRedirect('/help/a-qr-label-is-broken');
        $this->assertSame(Article::where('slug', 'a-qr-label-is-broken')->value('id'), $q->fresh()->article_id);
        $this->actingAs($w)->get('/help?q=qr+broken')->assertSee('A QR label is broken');
    }

    public function test_admin_writes_articles_with_translation_and_uploads_forms(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode([
            'nl' => ['title' => 'Een kabel testen', 'body' => 'Gebruik de tester.'], 'fr' => ['title' => 'Tester un câble', 'body' => 'Utilisez le testeur.'], 'es' => ['title' => 'Probar un cable', 'body' => 'Usa el comprobador.'],
        ])]]])]);
        $admin = $this->as('admin');
        $this->actingAs($admin)->post('/help/articles', ['topic' => 'warehouse', 'active' => 1, 'translate' => 1, 'title' => ['en' => 'Testing a cable', 'nl' => 'Kabel testen (eigen)'], 'body' => ['en' => 'Use the tester.']])->assertRedirect();
        $a = Article::where('slug', 'testing-a-cable')->firstOrFail();
        $this->assertSame('Kabel testen (eigen)', $a->title['nl'], 'own text is kept');
        $this->assertSame('Tester un câble', $a->title['fr']);

        $this->actingAs($admin)->post('/help/files', ['title' => 'Damage report form', 'file' => UploadedFile::fake()->create('damage.pdf', 40, 'application/pdf')])->assertRedirect();
        $f = HelpFile::firstOrFail();
        $sales = $this->as('sales');
        $this->actingAs($sales)->get('/help')->assertSee('Damage report form')->assertSee('PDF');
        $this->actingAs($sales)->get('/help/files/'.$f->id)->assertOk()->assertDownload('damage.pdf');
        $this->actingAs($sales)->post('/help/files', ['title' => 'x', 'file' => UploadedFile::fake()->create('x.pdf', 1)])->assertForbidden();

        // Hidden articles are not shown to colleagues.
        $a->update(['active' => false]);
        $this->actingAs($sales)->get('/help/testing-a-cable')->assertNotFound();
    }

    public function test_menu_link_and_shortcuts(): void
    {
        $u = $this->as('warehouse');
        $this->actingAs($u)->get('/dashboard')->assertOk()->assertSee('keydown', false)->assertSee('\u0022?\u0022', false);
        $this->actingAs($u)->get('/help')->assertSee('Keyboard shortcuts')->assertSee('<kbd>g</kbd>', false);
    }
}
