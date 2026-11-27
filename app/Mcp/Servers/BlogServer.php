<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateDraft;
use App\Mcp\Tools\CreateTranslation;
use App\Mcp\Tools\GetContent;
use App\Mcp\Tools\ListTranslationGaps;
use App\Mcp\Tools\ManageTaxonomy;
use App\Mcp\Tools\RequestPublish;
use App\Mcp\Tools\SearchContent;
use App\Mcp\Tools\UpdateContent;
use App\Mcp\Tools\UploadMedia;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/** The blog's MCP server for AI connections (JYU-554). Tools are listed according to the connection's permissions. */
#[Name('JYu Notes')]
#[Version('1.0.0')]
#[Instructions('Tools for JYu\'s multilingual blog (zh, en, ja, vi, id). Content is posts (long articles), tweets (short notes) and pages; bodies are Markdown. You can read everything including drafts, create and edit drafts and translations, organise tags / categories / media, and ask the author to publish or hide content. You cannot delete anything or publish directly. Every edit is kept in the version history.')]
class BlogServer extends Server
{
    protected array $tools = [
        SearchContent::class,
        GetContent::class,
        ListTranslationGaps::class,
        CreateDraft::class,
        UpdateContent::class,
        CreateTranslation::class,
        ManageTaxonomy::class,
        UploadMedia::class,
        RequestPublish::class,
    ];
}
