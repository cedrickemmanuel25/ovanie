// Connects only to a separately launched, local headless Chrome test process.
const fs=require('node:fs');
(async()=>{
 const targets=await(await fetch('http://127.0.0.1:9338/json')).json();
 const socket=new WebSocket(targets.find(t=>t.type==='page').webSocketDebuggerUrl);
 await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));
 let serial=0;const pending=new Map();socket.addEventListener('message',event=>{const data=JSON.parse(event.data);if(data.id){const promise=pending.get(data.id);pending.delete(data.id);data.error?promise.reject(new Error(data.error.message)):promise.resolve(data.result);}});
 const call=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,{resolve,reject});socket.send(JSON.stringify({id,method,params}));});
 await call('Page.enable');
 for(const [size,width,height] of [['mobile',390,844],['desktop',Number(process.argv[4])||1448,Number(process.argv[5])||(process.argv[4]?941:1086)]]){
  if(process.argv[3] && process.argv[3]!==size)continue;
  await call('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:size==='mobile'});
  for(const page of (process.argv[2]?process.argv[2].split(','):['dashboard','mission','assign','deliveries','map'])){
   await call('Page.navigate',{url:`http://127.0.0.1:8017/preview/${page}.html`});
   let result;
   for(let tries=0;tries<25;tries++){
    await new Promise(resolve=>setTimeout(resolve,300));
    result=await call('Runtime.evaluate',{expression:"document.getElementById('supervision-qa-results')?.textContent",returnByValue:true});
    if(result.result.value)break;
   }
   const metrics=await call('Runtime.evaluate',{expression:'JSON.stringify({width:innerWidth,scroll:document.documentElement.scrollWidth})',returnByValue:true});
   if(JSON.parse(metrics.result.value).width!==width){const overflow=await call('Runtime.evaluate',{expression:`JSON.stringify([...document.querySelectorAll('main *')].map(el=>({tag:el.tagName,cls:el.className,right:el.getBoundingClientRect().right,width:el.getBoundingClientRect().width})).filter(el=>el.right>${width}).slice(0,25))`,returnByValue:true});console.log('Overflow',overflow.result.value);}
   const shot=await call('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
   fs.writeFileSync(`storage/app/operations-visual/${page}-${size}.png`,Buffer.from(shot.data,'base64'));
   const checks=JSON.parse(result.result.value||'[]');checks.push({name:'Requested viewport width',pass:JSON.parse(metrics.result.value).width===width});fs.writeFileSync(`storage/app/operations-visual/results-${page}-${size}.json`,JSON.stringify(checks,null,2));
   console.log(page,size,metrics.result.value,checks.filter(c=>!c.pass));
  }
 }
 await call('Browser.close');socket.close();
})().catch(error=>{console.error(error.message);process.exit(1);});
