<?php

$wgMainCacheType = CACHE_MEMCACHED;
$wgMemCachedServers = array( "__MEMCACHED_HOST__:__MEMCACHED_PORT__" );

$wgSessionCacheType = CACHE_MEMCACHED;
# Turn this option back on if we use memcached
$wgUseDatabaseMessages = true;
