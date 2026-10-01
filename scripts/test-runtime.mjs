// Execute a PHP test only in an explicitly allowed, disposable WordPress root.
import fs from 'node:fs';
import path from 'node:path';
const root=path.resolve(new URL('..',import.meta.url).pathname);
if(process.env.HAMRAH_TEST_ALLOW!=='1')throw new Error('Set HAMRAH_TEST_ALLOW=1 only for a disposable WordPress database, never a real shop.');
const wpRoot=path.resolve(process.env.HAMRAH_TEST_WP_ROOT||path.join(root,'.cache/runtime/wordpress'));
if(!fs.existsSync(path.join(wpRoot,'wp-load.php')))throw new Error('Install a real isolated WordPress/WooCommerce instance first.');
const {PHP}=await import('../.cache/tools/node_modules/@php-wasm/universal/index.js');
const {loadNodeRuntime,useHostFilesystem}=await import('../.cache/tools/node_modules/@php-wasm/node/index.js');
const php=new PHP(await loadNodeRuntime(process.env.TEST_PHP||'8.3',{emscriptenOptions:{processId:process.pid}}));useHostFilesystem(php);
const test=path.resolve(root,process.argv[2]||'tests/integration.php');
const response=await php.run({code:`<?php define('HAMRAH_TEST_ALLOW',true);define('HAMRAH_TEST_WP_ROOT',base64_decode('${Buffer.from(wpRoot).toString('base64')}'));require base64_decode('${Buffer.from(test).toString('base64')}');`});
process.stdout.write(response.text);if(response.errors)process.stderr.write(response.errors);if(response.exitCode)process.exitCode=response.exitCode;php.exit();
