// Development-only syntax/assets checks. Tools live outside distribution paths.
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
const root = path.resolve(new URL('..', import.meta.url).pathname);
const { PHP } = await import('../.cache/tools/node_modules/@php-wasm/universal/index.js');
const { loadNodeRuntime, useHostFilesystem } = await import('../.cache/tools/node_modules/@php-wasm/node/index.js');
const { default: postcss } = await import('../.cache/tools/node_modules/postcss/lib/postcss.mjs');
const paths=[];
const walk=directory=>{for(const entry of fs.readdirSync(directory,{withFileTypes:true})){const p=path.join(directory,entry.name);if(entry.isDirectory())walk(p);else paths.push(p);}};
walk(path.join(root,'plugin'));walk(path.join(root,'theme'));
const phpFiles=paths.filter(p=>p.endsWith('.php'));
for(const version of ['8.1','8.3']){
 const php=new PHP(await loadNodeRuntime(version,{emscriptenOptions:{processId:process.pid}}));useHostFilesystem(php);
 const encoded=Buffer.from(JSON.stringify(phpFiles)).toString('base64');
 const response=await php.run({code:`<?php $files=json_decode(base64_decode('${encoded}'),true);foreach($files as $file){try{token_get_all(file_get_contents($file),TOKEN_PARSE);}catch(ParseError $error){fwrite(STDERR,$file.': '.$error->getMessage());exit(1);}}echo 'PHP '.PHP_VERSION;`});
 if(response.exitCode)throw new Error(response.errors||response.text);console.log(response.text,phpFiles.length+' source files parsed');php.exit();
}
for(const file of paths.filter(p=>p.endsWith('.js'))){const r=spawnSync(process.execPath,['--check',file],{encoding:'utf8'});if(r.status)throw new Error(r.stderr);console.log('JS OK',path.relative(root,file));}
for(const file of paths.filter(p=>p.endsWith('.css'))){postcss.parse(fs.readFileSync(file,'utf8'),{from:file});console.log('CSS OK',path.relative(root,file));}
JSON.parse(fs.readFileSync(path.join(root,'theme/hamrah-shop/theme.json'),'utf8'));
console.log('theme.json OK');
