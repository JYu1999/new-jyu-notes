<?php

namespace App\Mcp\Tools;

use App\Models\User;
use App\Services\MediaService;
use App\Support\AiPermissions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('upload_media')]
#[Description('Upload an image or video (base64, max 10 MB: jpg, png, webp, gif, mp4, webm) to the media library. Returns the URL and Markdown to use in a body.')]
class UploadMedia extends BlogTool
{
    protected string $permission = AiPermissions::ORGANIZE;

    public function handle(Request $request, MediaService $media): Response
    {
        if ($denied = $this->denied()) {
            return $denied;
        }

        $data = $request->validate([
            'filename' => 'required|string|max:200',
            'data' => 'required|string',
            'alt' => 'nullable|string|max:200',
        ]);
        $bytes = base64_decode(preg_replace('/^data:[^,]+,/', '', $data['data']), true);
        if ($bytes === false || $bytes === '') {
            return Response::error('data 不是有效的 base64。');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'mcp');
        file_put_contents($tmp, $bytes);
        try {
            $file = new UploadedFile($tmp, basename($data['filename']), null, null, true);
            $check = Validator::make(['file' => $file], ['file' => 'required|file|max:10240|mimes:jpg,jpeg,png,webp,gif,mp4,webm']);
            if ($check->fails()) {
                return Response::error($check->errors()->first('file'));
            }
            $saved = $media->upload($file, User::find($this->connection()?->user_id));
        } finally {
            @unlink($tmp);
        }

        $url = media_url($saved->path);
        $markdown = str_starts_with($saved->mime_type, 'video/')
            ? '<video class="local-video" controls src="'.$url.'" preload="metadata"></video>'
            : '!['.($data['alt'] ?? '').']('.$url.')';

        return Response::json(['id' => $saved->id, 'path' => $saved->path, 'url' => $url, 'markdown' => $markdown]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'filename' => $schema->string()->description('e.g. diagram.png')->required(),
            'data' => $schema->string()->description('Base64 file content')->required(),
            'alt' => $schema->string()->description('Alt text for the returned Markdown'),
        ];
    }
}
