<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Post;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class PostController extends Controller
{
    // List all posts
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);
        $sortBy = $request->query('sort', 'created_at'); // created_at, views, likes, trending
        $category = $request->query('category');

        $query = Post::with('user')->withCount('likes')->withCount('comments');

        // Filter by category if provided
        if ($category && $category !== 'all') {
            $query->where('category', $category);
        }

        // Apply sorting
        switch ($sortBy) {
            case 'views':
                $query->orderByDesc('views');
                break;
            case 'likes':
                $query->orderByDesc('likes_count');
                break;
            case 'trending':
                // Simple trending algorithm: combine recent posts with high engagement
                $query->selectRaw('*, (likes_count * 2 + comments_count + views/10 + (CASE WHEN created_at > DATE("now", "-7 days") THEN 10 ELSE 0 END)) as trending_score')
                      ->orderByDesc('trending_score');
                break;
            case 'oldest':
                $query->orderBy('created_at');
                break;
            default: // 'created_at' or 'latest'
                $query->orderByDesc('created_at');
                break;
        }

        $posts = $query->paginate($perPage);
        $postsArr = $posts->items();

        return response()->json([
            'data' => $postsArr,
            'current_page' => $posts->currentPage(),
            'last_page' => $posts->lastPage(),
            'total' => $posts->total(),
        ]);
    }

    // Show a single post
    public function show($id, Request $request)
    {
        $post = Post::with('user')->find($id);
        if (!$post) {
            return response()->json([
                'success' => false,
                'error' => 'The requested post does not exist or has been removed.'
            ], 404);
        }
        // Unique view tracking by IP in the database
        $ip = $request->ip();
        $viewExists = DB::table('post_views')->where('post_id', $id)->where('ip', $ip)->exists();
        if (!$viewExists) {
            $post->increment('views');
            DB::table('post_views')->insert(['post_id' => $id, 'ip' => $ip, 'created_at' => now(), 'updated_at' => now()]);
        }
        return response()->json([
            'success' => true,
            'data' => $post
        ]);
    }

    // Create a new post
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication required. Please log in to create posts.'
            ], 401);
        }

        // Only admin users can create posts
        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'error' => 'Only administrators can create posts. Please contact an admin if you need to publish content.'
            ], 403);
        }
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'content' => 'required|string|max:250000',
                'category' => 'required|string|in:news,review,podcast,opinion,lifestyle',
                'is_featured' => 'sometimes|boolean',
                'image' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp|max:51200', // 50MB
            ], [
                'title.required' => 'Post title is required.',
                'title.string' => 'Post title must be a valid text string.',
                'title.max' => 'Post title cannot exceed 255 characters.',
                'content.required' => 'Post content is required.',
                'content.string' => 'Post content must be a valid text string.',
                'content.max' => 'Post content cannot exceed 250000 characters.',
                'category.required' => 'Post category is required.',
                'category.string' => 'Post category must be a valid text string.',
                'category.in' => 'Post category must be one of: news, review, podcast, opinion, lifestyle.',
                'is_featured.boolean' => 'Featured status must be true or false.',
                'image.file' => 'Image must be a valid file.',
                'image.mimes' => 'Image must be a JPEG, JPG, PNG, GIF, or WEBP file.',
                'image.max' => 'Image size cannot exceed 50MB.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Post creation validation failed. Please check the provided data.',
                'errors' => $e->errors()
            ], 422);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            // Double-check MIME type server-side
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file->getMimeType(), $allowedMimes)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid image file type. Only JPEG, PNG, GIF, or WEBP images are allowed.'
                ], 422);
            }
            $imagePath = $file->store('posts', 'public');
        }

        // Only admins can set is_featured
        $isFeatured = false;
        if ($user->role === 'admin' && isset($validated['is_featured'])) {
            // Convert string to boolean properly
            $isFeatured = filter_var($validated['is_featured'], FILTER_VALIDATE_BOOLEAN);
        }

        $post = Post::create([
            'user_id' => $user->id,
            'title' => $validated['title'],
            'content' => $validated['content'],
            'category' => $validated['category'] ?? 'news',
            'is_featured' => $isFeatured,
            'image' => $imagePath,
        ]);

        $post->load('user');
        return response()->json(['success' => true, 'post' => $post]);
    }

    // Update a post
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication required. Please log in to update posts.'
            ], 401);
        }

        $post = Post::find($id);
        if (!$post) {
            return response()->json([
                'success' => false,
                'error' => 'The post you are trying to update does not exist.'
            ], 404);
        }

        if ($user->id !== $post->user_id && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'error' => 'You can only update your own posts unless you are an admin.'
            ], 403);
        }

        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'content' => 'required|string|max:250000',
                'category' => 'sometimes|string|in:news,review,podcast,opinion,lifestyle',
                'is_featured' => 'sometimes|boolean',
                'image' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp|max:51200', // 50MB
            ], [
                'title.required' => 'Post title is required.',
                'title.string' => 'Post title must be a valid text string.',
                'title.max' => 'Post title cannot exceed 255 characters.',
                'content.required' => 'Post content is required.',
                'content.string' => 'Post content must be a valid text string.',
                'content.max' => 'Post content cannot exceed 250000 characters.',
                'category.string' => 'Post category must be a valid text string.',
                'category.in' => 'Post category must be one of: news, review, podcast, opinion, lifestyle.',
                'is_featured.boolean' => 'Featured status must be true or false.',
                'image.file' => 'Image must be a valid file.',
                'image.mimes' => 'Image must be a JPEG, JPG, PNG, GIF, or WEBP file.',
                'image.max' => 'Image size cannot exceed 50MB.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Post update validation failed. Please check the provided data.',
                'errors' => $e->errors()
            ], 422);
        }

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            // Double-check MIME type server-side
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file->getMimeType(), $allowedMimes)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Only JPEG, PNG, GIF, or WEBP images are allowed.'
                ], 422);
            }

            // Delete old image if exists
            if ($post->image) {
                Storage::disk('public')->delete($post->image);
            }
            $post->image = $file->store('posts', 'public');
        }

        $post->title = $validated['title'];
        $post->content = $validated['content'];
        if (isset($validated['category'])) {
            $post->category = $validated['category'];
        }
        // Only admins can set is_featured
        if (isset($validated['is_featured']) && $user->role === 'admin') {
            // Convert string to boolean properly
            $post->is_featured = filter_var($validated['is_featured'], FILTER_VALIDATE_BOOLEAN);
        }
        $post->save();
        $post->load('user');

        return response()->json([
            'success' => true,
            'post' => $post,
            'message' => 'Post updated successfully.'
        ]);
    }

    // Delete a post
    public function destroy($id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication required. Please log in to delete posts.'
            ], 401);
        }

        $post = Post::find($id);
        if (!$post) {
            return response()->json([
                'success' => false,
                'error' => 'The post you are trying to delete does not exist.'
            ], 404);
        }

        if ($user->id !== $post->user_id && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'error' => 'You can only delete your own posts unless you are an admin.'
            ], 403);
        }

        // Delete associated image if exists
        if ($post->image) {
            Storage::disk('public')->delete($post->image);
        }

        $post->delete();

        return response()->json([
            'success' => true,
            'message' => 'Post deleted successfully.'
        ]);
    }

    // Trending posts with improved algorithm
    public function trending()
    {
        $posts = Post::with('user')
            ->selectRaw('posts.*,
                (select count(*) from likes where posts.id = likes.post_id) as likes_count,
                (select count(*) from comments where posts.id = comments.post_id) as comments_count,
                (
                    (select count(*) from likes where posts.id = likes.post_id) * 3 +
                    (select count(*) from comments where posts.id = comments.post_id) * 2 +
                    COALESCE(views, 0) / 20 +
                    (CASE WHEN created_at > DATE("now", "-7 days") THEN 15 ELSE 0 END) +
                    (CASE WHEN created_at > DATE("now", "-1 day") THEN 10 ELSE 0 END)
                ) as trending_score')
            ->orderByDesc('trending_score')
            ->orderByDesc('created_at') // Secondary sort for ties
            ->take(10)
            ->get();
        return response()->json($posts);
    }

    // Featured posts endpoint
    public function featured()
    {
        $posts = Post::with('user')
            ->withCount('likes')
            ->withCount('comments')
            ->where('is_featured', true)
            ->orWhere(function($query) {
                // Auto-feature posts with high engagement if no manual featured posts
                $query->selectRaw('*, (likes_count * 2 + comments_count + views/10) as engagement_score')
                      ->orderByDesc('engagement_score')
                      ->limit(5);
            })
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        // If no featured posts found, get recent high-engagement posts
        if ($posts->isEmpty()) {
            $posts = Post::with('user')
                ->withCount('likes')
                ->withCount('comments')
                ->where('image', '!=', null) // Prefer posts with images for featured
                ->orderByDesc('views')
                ->orderByDesc('likes_count')
                ->take(5)
                ->get();
        }

        return response()->json($posts);
    }

    // Get categories
    public function categories()
    {
        $categories = Post::select('category')
            ->groupBy('category')
            ->orderBy('category')
            ->pluck('category')
            ->toArray();

        return response()->json([
            'categories' => $categories
        ]);
    }
}
