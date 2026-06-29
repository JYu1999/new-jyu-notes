<?php

/*
| Single source of truth for API token abilities. Ability strings are
| "resource:action". Used by the token admin UI (checkbox grid), the
| ApiTokenService validation, and the Agent Skill docs (P4).
*/
return [
    'posts' => ['read', 'create', 'update', 'delete', 'publish'],
    'tweets' => ['read', 'create', 'update', 'delete', 'publish'],
    'categories' => ['read', 'create', 'update', 'delete'],
    'tags' => ['read', 'create', 'update', 'delete'],
    'media' => ['read', 'create', 'delete'],
    'todos' => ['read', 'create', 'update', 'delete'],
];
