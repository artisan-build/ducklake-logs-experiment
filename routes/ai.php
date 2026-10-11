<?php

use App\Http\Middleware\AuthenticateMcp;
use App\Mcp\Servers\LogServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', LogServer::class)->middleware(AuthenticateMcp::class);
