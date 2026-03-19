# VM Hang Fixes - Applied Changes

## Issues Identified

1. **Xdebug Enabled by Default** - Major performance killer and memory leak source
2. **PHP-FPM Process Recycling** - Processes not recycling frequently enough (500 requests)
3. **Unbounded Log Growth** - PHP-FPM and Nginx logs not being rotated
4. **No Process Memory Leak Protection** - Processes accumulating memory over time

## Fixes Applied

### 1. Disabled Xdebug (trellis/group_vars/development/php.yml)
- **Before**: `xdebug_mode: 'debug'` (always on)
- **After**: `xdebug_mode: 'off'` (disabled by default)
- **Impact**: Reduces memory usage and prevents performance degradation
- **Note**: Enable only when actively debugging

### 2. Increased PHP-FPM Process Recycling (trellis/group_vars/development/php.yml)
- **Before**: `php_fpm_pm_max_requests: 500` (from defaults)
- **After**: `php_fpm_pm_max_requests: 200`
- **Impact**: Processes recycle more frequently, preventing memory leak accumulation
- **Why**: Lower value forces processes to restart more often, clearing memory leaks

### 3. Added Log Rotation (trellis/group_vars/all/logrotate.yml)
- **Added**: PHP-FPM log rotation (daily, 100M max, 7 days retention)
- **Added**: Nginx log rotation (daily, 100M max, 7 days retention)
- **Impact**: Prevents log files from consuming all disk space
- **Why**: Unbounded log growth can fill disk and cause system hangs

## Next Steps

1. **Apply Changes**: Run `vagrant provision` or `trellis provision` to apply these fixes
2. **Restart Services**: After provisioning, restart PHP-FPM and Nginx:
   ```bash
   vagrant ssh
   sudo systemctl restart php8.3-fpm
   sudo systemctl restart nginx
   ```
3. **Monitor**: Watch for improvements in VM stability over time

## Additional Recommendations

If issues persist, consider:
- Reducing `php_fpm_pm_max_children` if memory is constrained
- Enabling PHP-FPM emergency restart thresholds
- Checking for WordPress plugins causing memory leaks
- Monitoring system resources: `htop`, `free -h`, `df -h`

