(function(){
"use strict";
const CHECKOUT="https://pay.cakto.com.br/35dgsa8_1077031";
function withParams(base){
 try{const url=new URL(base,location.href);const incoming=new URLSearchParams(location.search);
 incoming.forEach((v,k)=>{if(!url.searchParams.has(k))url.searchParams.set(k,v);});
 return url.href;}catch(_){return base;}
}
function track(name,data){
 try{if(typeof window.fbq==="function")window.fbq("trackCustom",name,Object.assign({funnel:"lp_v3"},data||{}));}catch(_){}
}
document.querySelectorAll("[data-checkout]").forEach(a=>{
 a.href=withParams(CHECKOUT);
 a.addEventListener("click",()=>track("lp_checkout_clicked",{position:a.dataset.checkout||"unknown"}));
});
document.querySelectorAll("[data-quizlink]").forEach(a=>{
 a.href=withParams(a.getAttribute("href"));
 a.addEventListener("click",()=>track("lp_quiz_clicked",{position:"lp"}));
});

// The video stays native and playable even if analytics or this enhancement fail.
// Its poster is fetched only near the section; the MP4 keeps preload="none".
const video=document.querySelector("video[data-poster]");
if(video){
 const showPoster=()=>{video.poster=video.dataset.poster;};
 if("IntersectionObserver" in window){
  const posterObserver=new IntersectionObserver(entries=>{
   if(entries.some(entry=>entry.isIntersecting)){showPoster();posterObserver.disconnect();}
  },{rootMargin:"400px 0px"});
  posterObserver.observe(video);
 }else showPoster();

 // Native played ranges count unique content, excluding skipped/repeated segments.
 // At most six analytics events per page load, with no polling or extra requests
 // to the hosting server. The existing Pixel handles delivery asynchronously.
 const milestones=[25,50,75,95],sent=new Set();
 let started=false,completed=false;
 const measure=()=>{
  const duration=video.duration;
  if(!started||!Number.isFinite(duration)||duration<=0)return null;
  const ranges=video.played;
  let watched=0;
  for(let i=0;i<ranges.length;i++){
   watched+=Math.max(0,Math.min(duration,ranges.end(i))-Math.max(0,ranges.start(i)));
  }
  watched=Math.min(duration,watched);
  return {video_id:"demonstracao",percent:watched/duration*100,
   duration_seconds:Math.round(duration*100)/100,watched_seconds:Math.round(watched*100)/100};
 };
 const updateProgress=()=>{
  const data=measure();if(!data)return null;
  milestones.forEach(percent=>{
   if(data.percent>=percent&&!sent.has(percent)){
    sent.add(percent);track("lp_video_progress",Object.assign({},data,{percent}));
   }
  });
  return data;
 };
 video.addEventListener("playing",()=>{
  if(!started){started=true;track("lp_video_started",{video_id:"demonstracao"});}
 });
 video.addEventListener("timeupdate",updateProgress);
 video.addEventListener("pause",updateProgress);
 video.addEventListener("ended",()=>{
  const data=updateProgress();
  // Allow 1% for native timing precision; reaching the end alone is insufficient.
  if(data&&data.percent>=99&&!completed){
   completed=true;track("lp_video_completed",Object.assign({},data,{percent:Math.round(data.percent*100)/100}));
  }
 });
}

const STEPS=[
 {img:"demo-1-despesas.b051ce2e0d6a.webp",alt:"Tela real com despesas fixas e quantidade de marmitas",title:"Primeiro, os gastos do seu negócio",desc:"Informe despesas fixas, quantos dias trabalha e quantas marmitas faz. A ferramenta calcula quanto desses custos corresponde a cada unidade."},
 {img:"demo-2-busca.0be5bf3d15ca.webp",alt:"Busca de ingredientes na lista da ferramenta",title:"Busque os ingredientes que você usa",desc:"Pesquise arroz, feijão, frango e outros ingredientes disponíveis na base. Se não encontrar, pode incluir um ingrediente personalizado."},
 {img:"demo-3-quantidade.86855942c0c7.webp",alt:"Tela real para informar o preço pago, peso e quantidade usada",title:"Diga quanto pagou e quanto usou",desc:"Informe o preço da compra, a unidade de medida e a quantidade de cada ingrediente que entrou na receita, além do custo da embalagem."},
 {img:"demo-4-calculo.639638a35764.webp",alt:"Tela com lista de ingredientes e botão Ver meu preço",title:"Revise a receita antes de calcular",desc:"A lista mostra os ingredientes adicionados, seus valores e a embalagem. Ao concluir, você avança para o cálculo do preço."},
 {img:"demo-5-preco.ef5d9735ec48.webp",alt:"Tela real de resultado com preço recomendado e custos por marmita",title:"Veja seu custo e o preço recomendado",desc:"Consulte a composição do custo, o limite mínimo e o preço recomendado, considerando os dados e a margem que você informou."}
];
const tabs=Array.from(document.querySelectorAll("[data-tab]"));
if(tabs.length){
 const img=document.getElementById("demo-img"),txt=document.getElementById("step-title"),desc=document.getElementById("step-desc"),index=document.getElementById("step-index"),panel=document.getElementById("demo-painel");
 function pick(i,focus){
  const item=STEPS[i];if(!item)return;
  tabs.forEach((t,j)=>{t.setAttribute("aria-selected",j===i?"true":"false");t.tabIndex=j===i?0:-1;});
  img.src="assets/"+item.img;img.alt=item.alt;txt.textContent=item.title;desc.textContent=item.desc;index.textContent="Etapa "+(i+1)+" de 5";panel.setAttribute("aria-labelledby","tab-"+i);
  if(focus)tabs[i].focus();
  track("lp_demo_step",{step:String(i+1)});
 }
 tabs.forEach((b,i)=>{
   b.addEventListener("click",()=>pick(i,false));
   b.addEventListener("keydown",e=>{
     if(!["ArrowLeft","ArrowRight","Home","End"].includes(e.key))return;
     e.preventDefault();const k=e.key==="Home"?0:e.key==="End"?tabs.length-1:(i+(e.key==="ArrowRight"?1:-1)+tabs.length)%tabs.length;pick(k,true);
   });
 });
}
const sticky=document.getElementById("mobile-sticky"),hero=document.querySelector(".hero"),offer=document.getElementById("comprar");
if(sticky&&hero&&"IntersectionObserver"in window){
 let heroVisible=true,offerVisible=false;
 const update=()=>sticky.classList.toggle("visible",!heroVisible&&!offerVisible);
 new IntersectionObserver(entries=>{heroVisible=entries[0].isIntersecting;update();},{threshold:0}).observe(hero);
 if(offer)new IntersectionObserver(entries=>{offerVisible=entries[0].isIntersecting;update();},{threshold:0}).observe(offer);
}
})();
