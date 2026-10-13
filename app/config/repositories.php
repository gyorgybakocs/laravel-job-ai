<?php

return [
    'per_page' => (int) env('GITHUB_REPOSITORIES_PER_PAGE', 15),
    'default_sort' => 'updated_desc',
    'default_search_field' => 'target',
    'statuses' => ['archived', 'active'],
    'search_fields' => ['target', 'description'],
    'sorts' => ['target_asc', 'target_desc', 'name_asc', 'name_desc', 'updated_desc', 'stars_desc'],
];
