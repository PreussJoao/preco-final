(function(){
"use strict";
const CHECKOUT="https://pay.cakto.com.br/35dgsa8_1077031";
// A/B test of the notices: half of the visits see them. ?popup=1 or ?popup=0 forces a group (for checking).
const SHARE_WITH_POPUP=0.5;
function popupGroup(){
 const forced=new URLSearchParams(location.search).get("popup");
 let g=forced==="1"?"com_popup":forced==="0"?"sem_popup":null;
 try{
  if(!g)g=localStorage.getItem("pc_popup_group");
  if(g!=="com_popup"&&g!=="sem_popup")g=Math.random()<SHARE_WITH_POPUP?"com_popup":"sem_popup";
  localStorage.setItem("pc_popup_group",g);
 }catch(_){if(!g)g=Math.random()<SHARE_WITH_POPUP?"com_popup":"sem_popup";}
 return g;
}
const GROUP=popupGroup();
const state={stage:null,price:null,costs:[],known:null,partial:null,current:0};
const stepNames=["quiz-intro","quiz-step-1","quiz-step-2","quiz-step-3","quiz-step-4","quiz-result"];
const by=id=>document.getElementById(id);
const money=n=>n.toLocaleString("pt-BR",{style:"currency",currency:"BRL"});
function parseBR(raw){
 let s=String(raw||"").replace(/R\$/gi,"").replace(/\s/g,"");
 if(!s||!/^\d+([.,]\d{1,2})?$/.test(s))return NaN;
 return Number(s.replace(",","."));
}
function withParams(base){try{let u=new URL(base,location.href);new URLSearchParams(location.search).forEach((v,k)=>{if(k!=="popup"&&!u.searchParams.has(k))u.searchParams.set(k,v)});return u.href;}catch(_){return base;}}
function track(event,data={}){try{if(typeof fbq==="function")fbq("trackCustom",event,Object.assign({funnel:"quiz_v3",popup_group:GROUP},data));}catch(_){}}
function go(n){
 state.current=n;
 stepNames.forEach((id,i)=>{const el=by(id);if(el)el.hidden=i!==n;});
 window.scrollTo({top:0,behavior:"instant"});
 const focus=by(n===0?"quiz-intro-title":n===5?"result-title":"step"+n+"-title");
 if(focus){focus.setAttribute("tabindex","-1");focus.focus({preventScroll:true});}
}
function error(n,show){const el=by("error-"+n);if(el)el.hidden=!show;}
document.querySelectorAll("[data-checkout]").forEach(a=>{
 a.href=withParams(CHECKOUT);
 a.addEventListener("click",()=>track("quiz_checkout_clicked",{position:a.dataset.checkout||"result"}));
});
document.querySelectorAll("[data-backlp]").forEach(a=>{
 a.href=withParams(a.getAttribute("href"));
});
const start=by("quiz-start");
if(start)start.addEventListener("click",()=>{track("quiz_started");go(1)});
document.querySelectorAll("[data-price]").forEach(b=>b.addEventListener("click",()=>{
 by("unit-price").value=Number(b.dataset.price).toFixed(2).replace(".",",");
 by("unit-price").focus();
 error(2,false);
}));
document.querySelectorAll('[name="stage"],[name="known"]').forEach(i=>i.addEventListener("change",()=>{
 error(i.name==="stage"?1:4,false);
 if(i.name==="known")by("partial-field").hidden=i.value==="no";
}));
document.querySelectorAll('[name="cost"]').forEach(i=>i.addEventListener("change",()=>{
 if(i.value==="unsure"&&i.checked)document.querySelectorAll('[name="cost"]:not([value="unsure"])').forEach(c=>c.checked=false);
 else if(i.value!=="unsure"&&i.checked)document.querySelector('[name="cost"][value="unsure"]').checked=false;
 error(3,false);
}));
document.querySelectorAll("[data-prev]").forEach(b=>b.addEventListener("click",()=>go(Number(b.dataset.prev))));
function validateStep(n){
 if(n===1){
  const sel=document.querySelector('[name="stage"]:checked');
  if(!sel){error(1,true);return false;}
  state.stage=sel.value;return true;
 }
 if(n===2){
  const v=parseBR(by("unit-price").value);
  if(!Number.isFinite(v)||v<1||v>500){error(2,true);return false;}
  state.price=Math.round(v*100)/100;return true;
 }
 if(n===3){
  const selected=Array.from(document.querySelectorAll('[name="cost"]:checked')).map(x=>x.value);
  if(!selected.length){error(3,true);return false;}
  state.costs=selected;return true;
 }
 if(n===4){
  const sel=document.querySelector('[name="known"]:checked');
  if(!sel){error(4,true);return false;}
  state.known=sel.value;state.partial=null;
  if(sel.value!=="no"&&by("partial-cost").value.trim()){
    const v=parseBR(by("partial-cost").value);
    if(!Number.isFinite(v)||v<=0||v>500){error(4,true);return false;}
    state.partial=Math.round(v*100)/100;
  }
  return true;
 }
 return false;
}
document.querySelectorAll("[data-next]").forEach(b=>b.addEventListener("click",()=>{
 const n=Number(b.dataset.next);
 if(!validateStep(n))return;
 track("quiz_question_answered",{step:String(n)});
 if(n===4){renderResult();track("quiz_completed",{profile:resultProfile()});go(5);track("quiz_result_viewed",{profile:resultProfile()});}
 else go(n+1);
}));
function resultProfile(){
 const essential=["ingredients","packaging","energy","labor","fixed"];
 const missing=essential.filter(x=>!state.costs.includes(x));
 if(state.stage==="starting")return "iniciante";
 if(state.costs.includes("unsure")||missing.length>=3)return "custos_revisar";
 if(missing.length)return "controle_parcial";
 return "controle_mais_completo";
}
const costLabels={ingredients:"ingredientes",packaging:"embalagem",energy:"gás e energia",labor:"tempo de trabalho",fixed:"despesas fixas"};
function renderResult(){
 const profile=resultProfile(),p=state.price;
 const beginner=state.stage==="starting";
 const prefix=beginner?"Você pretende cobrar":"Você informou que cobra";
 by("result-intro").textContent=prefix+" "+money(p)+" por marmita. Este diagnóstico considera o que você marcou, mas não sabe se os valores dos seus custos estão corretos.";
 const headlines={
  iniciante:["Começando a vender","Organize os custos antes de definir o preço","Como você ainda está começando, vale simular ingredientes, embalagens, despesas e mão de obra para não basear o preço só na concorrência."],
  custos_revisar:["Custos para conferir","Talvez sua conta ainda tenha lacunas","Pelas suas respostas, você não confirmou vários custos. Isso não prova prejuízo: indica o que vale verificar antes de definir o preço."],
  controle_parcial:["Alguns custos podem faltar","Você já acompanha parte da conta","Você marcou alguns gastos importantes. Vale revisar os demais custos aplicáveis para não confundir dinheiro que entra com lucro."],
  controle_mais_completo:["Boa base de precificação","Você já considera os principais grupos de custos","Você marcou os grupos principais. O próximo passo é calcular os valores exatos por unidade, o rendimento e a margem pretendida."]
 };
 const h=headlines[profile];
 by("profile-label").textContent=h[0];by("profile-title").textContent=h[1];by("profile-detail").textContent=h[2];
 const missing=["ingredients","packaging","energy","labor","fixed"].filter(x=>!state.costs.includes(x));
 if(state.costs.includes("unsure")){
  by("missing-box").textContent="Você informou que não tem certeza de quais custos considera. A calculadora ajuda a organizar essas informações, mas você precisa informar os valores reais.";
 }else if(missing.length){
  const names=missing.map(x=>costLabels[x]);
  by("missing-box").textContent="Vale conferir se estes itens se aplicam ao seu negócio e já estão no preço: "+names.join(", ")+". Não marcar um custo aqui não significa necessariamente que ele esteja ausente do seu cálculo.";
 }else{
  by("missing-box").textContent="Você marcou os cinco grupos principais de custos. Isso é um bom começo. Confira se cada valor está correto e se considerou entrega e taxas quando existirem.";
 }
 const partial=by("partial-summary");
 if(state.partial!==null){
  partial.hidden=false;
  by("result-price").textContent=money(p);
  by("result-cost").textContent=money(state.partial);
  const remainder=Math.round((p-state.partial)*100)/100,box=by("remainder-box");
  box.classList.toggle("negative",remainder<0);
  by("result-remainder").textContent=(remainder<0?"−":"")+money(Math.abs(remainder));
  by("remainder-note").textContent=remainder<0?
   "O custo informado de comida e embalagem já supera o preço informado. Confira os dois números; os demais gastos ainda não entraram.":
   "Isso NÃO é lucro: ainda faltam mão de obra, energia, despesas fixas e outros custos aplicáveis.";
 }else partial.hidden=true;
}
const restart=by("restart");
if(restart)restart.addEventListener("click",()=>{
 Object.assign(state,{stage:null,price:null,costs:[],known:null,partial:null,current:0});
 document.querySelectorAll('input[type="checkbox"],input[type="radio"]').forEach(i=>i.checked=false);
 by("unit-price").value="";by("partial-cost").value="";by("partial-field").hidden=true;
 [1,2,3,4].forEach(n=>error(n,false));
 track("quiz_restart");go(0);
});
go(0);
})();