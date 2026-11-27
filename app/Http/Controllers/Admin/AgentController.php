<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Admin "AI 連線" page (JYU-554): how to connect an AI to the blog over MCP. */
class AgentController extends Controller
{
    public function index(): View
    {
        return view('admin.agents.index', [
            'mcpUrl' => url('/mcp'),
        ]);
    }
}
