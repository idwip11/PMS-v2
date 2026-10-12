// Uses an isolated Chrome --headless profile with CDP on 127.0.0.1:9227.
// The app must run at localhost:8082 against the guarded stage-6 schema clone.
const fs=require('fs');
(async()=>{
 const target=(await(await fetch('http://127.0.0.1:9227/json/list')).json()).find(t=>t.type==='page');
 const ws=new WebSocket(target.webSocketDebuggerUrl);let id=0;const waiting=new Map();const errors=[];
 await new Promise((resolve,reject)=>{ws.onopen=resolve;ws.onerror=reject;});
 ws.onmessage=e=>{const m=JSON.parse(e.data);if(waiting.has(m.id)){const p=waiting.get(m.id);waiting.delete(m.id);m.error?p.reject(new Error(m.error.message)):p.resolve(m.result);}if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.text+' '+(m.params.exceptionDetails.exception?.description||''));};
 const send=(method,params={})=>new Promise((resolve,reject)=>{const key=++id;waiting.set(key,{resolve,reject});ws.send(JSON.stringify({id:key,method,params}));});
 const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw new Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value;};
 const wait=async(expression,label)=>{for(let n=0;n<120;n++){await new Promise(r=>setTimeout(r,250));if(await evaluate(expression))return;}throw new Error('Timed out: '+label);};
 const navigate=async path=>{await send('Page.navigate',{url:'http://localhost:8082'+path});await wait(`location.href.startsWith('http://localhost:8082')&&document.readyState==='complete'`,'navigate '+path);await evaluate('window.scrollTo(0,0)');};
 await send('Page.enable');await send('Runtime.enable');
 const dir='/tmp/mitra-stage6-browser';fs.mkdirSync(dir,{recursive:true});const audits=[];
 const login=async user=>{await send('Network.clearBrowserCookies');await navigate('/login');await evaluate(`document.querySelector('[name=username]').value=${JSON.stringify(user)};document.querySelector('[name=password]').value='Mitra-Stage6-Only!';document.querySelector('button[type=submit]').click()`);await wait(`location.pathname!='/login'&&document.readyState==='complete'`,'login '+user);};
 const select=async prop=>{await evaluate(`const p=document.querySelector('#active-property-select');p.value='${prop}';p.dispatchEvent(new Event('change',{bubbles:true}))`);await wait(`document.readyState==='complete'&&document.querySelector('#active-property-select')?.value==='${prop}'`,'switch property');};
 const audit=async(page,device,width)=>{
   await new Promise(r=>setTimeout(r,500));
   const metrics=await evaluate(`(()=>({modal:document.querySelector('#dynamicModal.show .modal-dialog')?(()=>{const r=document.querySelector('#dynamicModal.show .modal-dialog').getBoundingClientRect();return {left:r.left,right:r.right,width:r.width}})():null,viewport:innerWidth,documentWidth:document.documentElement.scrollWidth,location:location.pathname,forms:[...document.querySelectorAll('main input:not([type=hidden]),main select,main button')].filter(e=>e.getBoundingClientRect().width>0).map(e=>({name:e.name||e.textContent.trim().slice(0,30),width:Math.round(e.getBoundingClientRect().width)})),tables:[...document.querySelectorAll('main .table-responsive')].map(e=>({width:e.clientWidth,content:e.scrollWidth}))}))()`);
   const result={page,device,...metrics};audits.push(result);
   const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});fs.writeFileSync(`${dir}/${page}-${device}.png`,Buffer.from(shot.data,'base64'));
   console.log(JSON.stringify({page,device,viewport:metrics.viewport,documentWidth:metrics.documentWidth}));
   if(metrics.viewport!==width||metrics.documentWidth>width+1||(metrics.modal&&(metrics.modal.left<0||metrics.modal.right>width+1)))throw new Error('Page overflow: '+JSON.stringify(result));
 };
 for(const[device,width,height]of[['small-phone',320,800],['phone',390,844],['tablet',768,1024],['tablet-landscape',1024,768],['desktop',1440,1050]]){
   await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<600});
   await login('stage6_1002');await navigate('/finance/owner?month=2026-10');await audit('owner',device,width);
   await login('stage6_1001');await select(2);
   await navigate('/finance?month=2026-10');await wait(`!!document.querySelector('#finance-panel table')`,'finance HTMX panel');await audit('finance',device,width);
   await navigate('/finance/owner-setup');await audit('setup',device,width);
   await navigate('/settings');await wait(`!!document.querySelector('#new-user-role')`,'settings HTMX panel');
   await evaluate(`const role=document.querySelector('#new-user-role');role.value='Owner Mitra';role.dispatchEvent(new Event('change',{bubbles:true}));if(document.querySelector('#new-user-property').value==='1')throw new Error('Owner Mitra UI selected HomiQ')`);
   await audit('settings',device,width);
   await select(3);await navigate('/reservations');await wait(`typeof htmx!=='undefined'`,'booking HTMX');await evaluate(`document.querySelector('[hx-get$="reservations/new"]').click()`);await wait(`!!document.querySelector('#dynamicModal.show .booking-modal')`,'booking modal');
   await audit('booking',device,width);
   await navigate('/finance/company?company=1&tab=monthly&month=2026-10');await wait(`document.body.innerText.includes('Holding Sintetis')&&document.querySelector('main table')`,'holding monthly report');await audit('holding',device,width);
 }
 if(errors.length)throw new Error('Runtime exceptions: '+JSON.stringify(errors));
 fs.writeFileSync(`${dir}/layout-audits.json`,JSON.stringify(audits,null,2));fs.writeFileSync(`${dir}/runtime-errors.json`,JSON.stringify(errors));
 ws.close();console.log('Stage 6 browser: '+audits.length+' page/viewport combinations passed.');
})().catch(e=>{console.error(e);process.exit(1);});
