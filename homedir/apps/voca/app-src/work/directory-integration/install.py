from pathlib import Path
root = Path('/Users/kariensmith/Sites/voca')
staged = Path('/Users/kariensmith/Sites/voca app/homedir/apps/voca/app-src/work/directory-integration/website')
p = root / 'routes/api.php'
s = p.read_text()
route_exists = 'public/directory' in s
if not route_exists:
 s += """
Route::get('/public/directory', [\\App\\Http\\Controllers\\PublicBusinessDirectoryController::class, 'index'])->middleware('throttle:60,1');
Route::get('/public/directory/{slug}', [\\App\\Http\\Controllers\\PublicBusinessDirectoryController::class, 'show'])->middleware('throttle:60,1');
"""
new_file = root / 'app/Http/Controllers/PublicBusinessDirectoryController.php'
if new_file.exists(): raise SystemExit('Directory API controller already exists; no changes applied.')
new_file.write_text((staged / 'app/Http/Controllers/PublicBusinessDirectoryController.php').read_text())
if not route_exists: p.write_text(s)
print('Public directory endpoints installed.')
