// Actual WooCommerce Store API, with native Cart-Token, not mock endpoints.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import {createRequire} from 'node:module';
const require=createRequire(new URL('../.cache/tools/package.json',import.meta.url));
const {request}=require('playwright');
const fixture=JSON.parse(fs.readFileSync(new URL('../.cache/runtime/fixture-manifest.json',import.meta.url)));
const context=await request.newContext({baseURL:process.env.HAMRAH_TEST_URL||'http://127.0.0.1:8080'});
const initial=await context.get('/wp-json/wc/store/v1/cart');assert.equal(initial.status(),200);
const token=initial.headers()['cart-token'];assert.ok(token);
const headers={'Cart-Token':token};
let response=await context.post('/wp-json/wc/store/v1/cart/add-item',{headers,data:{id:fixture.base,quantity:1}});assert.equal(response.status(),201,await response.text());
const address={first_name:'آزمون',last_name:'موقت',company:'',address_1:'نشانی آزمون موقت',address_2:'',city:'تهران',state:'THR',postcode:'۱۲۳۴۵۶۷۸۹۰',country:'IR',phone:'۰۹۱۲۳۴۵۶۷۸۹'};
response=await context.post('/wp-json/wc/store/v1/cart/update-customer',{headers,data:{billing_address:{...address,email:'hs-storeapi@example.test'},shipping_address:address}});
assert.equal(response.status(),200,await response.text());let data=await response.json();assert.equal(data.billing_address.phone,'+989123456789');assert.equal(data.billing_address.postcode,'1234567890');
// A valid native landline is intentionally not a valid mobile for an Iranian checkout.
response=await context.post('/wp-json/wc/store/v1/checkout',{headers,data:{billing_address:{...address,phone:'02112345678',email:'hs-storeapi@example.test'},shipping_address:address,payment_method:'cod',payment_data:[]}});
assert.ok(response.status()>=400 && response.status()<500,'Invalid Iranian mobile must not complete an order');
response=await context.post('/wp-json/wc/store/v1/checkout',{headers,data:{billing_address:{...address,email:'hs-storeapi@example.test'},shipping_address:address,payment_method:'cod',payment_data:[]}});
assert.equal(response.status(),200,await response.text());data=await response.json();assert.ok(data.order_id>0);assert.equal(data.billing_address.phone,'+989123456789');assert.equal(data.billing_address.postcode,'1234567890');assert.equal(data.payment_result.payment_status,'success');
fs.writeFileSync(new URL('../.cache/store-api-results.json',import.meta.url),JSON.stringify({cart:true,normalization:true,invalidMobileRejected:true,checkout:true,orderId:data.order_id},null,2));
console.log('PASS: actual Store API cart, Persian digits, invalid mobile rejection, native COD checkout.');
await context.dispose();
