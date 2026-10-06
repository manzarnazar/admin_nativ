<?php

namespace App\Http\Controllers\Api;

use App\Enums\BlogStatus;
use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\BlogCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    /**
     * List blogs with search, filter, and pagination
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:blog_categories,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        $limit = $request->input('limit', 9);
        $offset = $request->input('offset', 0);
        $page = (int) ($offset / $limit) + 1;

        $query = Blog::query()
            ->published()
            ->with('category:id,name,slug')
            ->orderByDesc('published_at');

        // Search in title, short_description, content
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if ($categoryId = $request->input('category_id')) {
            $query->where('blog_category_id', $categoryId);
        }

        $blogs = $query->paginate($limit, ['*'], 'page', $page);

        $items = $blogs->map(fn (Blog $blog) => [
            'id' => $blog->id,
            'slug' => $blog->slug,
            'title' => $blog->title,
            'excerpt' => $blog->short_description,
            'featured_image' => $blog->cover_image ? asset('storage/'.$blog->cover_image) : null,
            'category' => [
                'id' => $blog->category->id,
                'name' => $blog->category->name,
                'slug' => $blog->category->slug,
            ],
            'published_at' => $blog->published_at?->format('M d, Y'),
            'read_time_minutes' => $blog->read_time_minutes,
            'meta_title' => $blog->meta_title,
            'meta_description' => $blog->meta_description,
            'meta_keywords' => $blog->meta_keywords,
            'schema_markup' => $blog->schema_markup,
        ])->toArray();

        return $this->paginatedResponse($blogs, $items, 'Blogs fetched successfully');
    }

    /**
     * Get single blog details with related blogs
     */
    public function show(string $slug): JsonResponse
    {


        $blog = Blog::query()
            ->published()
            ->with('category:id,name,slug')
            ->where('slug', $slug)
            ->firstOrFail();

        // Get related blogs from same category
        $relatedQuery = Blog::query()
            ->published()
            ->where('id', '!=', $blog->id)
            ->where('blog_category_id', $blog->blog_category_id)
            ->with('category:id,name,slug')
            ->orderByDesc('published_at')
            ->limit(4);

        $relatedBlogs = $relatedQuery->get();

        // If less than 4, fill with any other published blogs
        if ($relatedBlogs->count() < 4) {
            $remaining = 4 - $relatedBlogs->count();
            $existingIds = $relatedBlogs->pluck('id')->push($blog->id);

            $fallbackBlogs = Blog::query()
                ->published()
                ->whereNotIn('id', $existingIds)
                ->with('category:id,name,slug')
                ->orderByDesc('published_at')
                ->limit($remaining)
                ->get();

            $relatedBlogs = $relatedBlogs->merge($fallbackBlogs);
        }

        return $this->successResponse([
            'id' => $blog->id,
            'slug' => $blog->slug,
            'title' => $blog->title,
            'excerpt' => $blog->short_description,
            'content' => $blog->content,
            'featured_image' => $blog->cover_image ? asset('storage/'.$blog->cover_image) : null,
            'category' => [
                'id' => $blog->category->id,
                'name' => $blog->category->name,
                'slug' => $blog->category->slug,
            ],
            'published_at' => $blog->published_at?->format('M d, Y'),
            'read_time_minutes' => $blog->read_time_minutes,
            'meta_title' => $blog->meta_title,
            'meta_description' => $blog->meta_description,
            'meta_keywords' => $blog->meta_keywords,
            'schema_markup' => $blog->schema_markup,
            'related_blogs' => $relatedBlogs->map(fn (Blog $b) => [
                'id' => $b->id,
                'slug' => $b->slug,
                'title' => $b->title,
                'excerpt' => $b->short_description,
                'featured_image' => $b->cover_image ? asset('storage/'.$b->cover_image) : null,
                'category' => [
                    'id' => $b->category->id,
                    'name' => $b->category->name,
                    'slug' => $b->category->slug,
                ],
                'published_at' => $b->published_at?->format('M d, Y'),
                'read_time_minutes' => $b->read_time_minutes,
            ]),
        ], 'Blog fetched successfully');
    }

    /**
     * List active blog categories with blog count
     */
    public function categories(): JsonResponse
    {
        $categories = BlogCategory::query()
            ->published()
            ->withCount(['blogs' => fn ($q) => $q->published()])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $categories->map(fn (BlogCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'blogs_count' => $category->blogs_count,
            ]),
        ]);
    }
}
