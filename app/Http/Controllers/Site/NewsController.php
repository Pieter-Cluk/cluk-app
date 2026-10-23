<?php

namespace App\Http\Controllers\Site;

use App\Models\Social\Post;
use App\Services\Website\SocialFeed;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** News: the Facebook and Instagram posts, filtered by part of the site (hashtag). */
class NewsController extends SiteController
{
    public const PER_PAGE = 24;

    public function index(Request $request): View
    {
        $this->locale($request);
        $part = in_array($request->query('part'), SocialFeed::SECTIONS, true) ? $request->query('part') : null;
        $list = SocialFeed::for($part);
        $page = max(1, $request->integer('page', 1));

        return view('site.news', [
            'part' => $part, 'counts' => SocialFeed::counts(),
            'posts' => new LengthAwarePaginator($list->slice(($page - 1) * self::PER_PAGE, self::PER_PAGE)->values(), $list->count(), self::PER_PAGE, $page),
        ]);
    }

    public function image(int $post): BinaryFileResponse
    {
        $file = SocialFeed::imageFile($this->find($post));
        abort_unless($file, 404);

        return response()->file($file, ['Cache-Control' => 'public, max-age=604800']);
    }

    public function video(int $post): BinaryFileResponse
    {
        $file = SocialFeed::videoFile($this->find($post));
        abort_unless($file, 404);

        return response()->file($file, ['Content-Type' => 'video/mp4', 'Cache-Control' => 'public, max-age=604800']);
    }

    private function find(int $id): Post
    {
        abort_unless(SocialFeed::all()->contains('id', $id), 404);

        return Post::findOrFail($id);
    }
}
