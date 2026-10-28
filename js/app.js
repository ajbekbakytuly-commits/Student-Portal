(function(){
var D=document.body.dataset;
var side=document.getElementById('side'),bg=document.getElementById('burger');
if(bg)bg.onclick=function(){side.classList.toggle('open')};
var modal=null;
function confirmBox(msg,ok){
  if(!modal){modal=document.createElement('div');modal.className='modal';
  modal.innerHTML='<div class="mbox"><h3>Растау</h3><p class="muted"></p><div class="actions"><button type="button" class="btn btn-ghost" data-no>Бас тарту</button><button type="button" class="btn btn-danger" data-yes>Иә, жалғастыру</button></div></div>';document.body.appendChild(modal);}
  modal.querySelector('p').textContent=msg;modal.classList.add('show');
  modal.querySelector('[data-no]').onclick=function(){modal.classList.remove('show')};
  modal.querySelector('[data-yes]').onclick=function(){modal.classList.remove('show');ok()};
}
document.addEventListener('submit',function(ev){var f=ev.target;
  if(f.dataset.confirm&&!f.dataset.ok){ev.preventDefault();confirmBox(f.dataset.confirm,function(){f.dataset.ok=1;f.submit()})}});
var gs=document.getElementById('gsearch');
document.querySelectorAll('table[data-table]').forEach(function(t){
  var rows=[].slice.call(t.tBodies[0].rows),per=+t.dataset.per||10,page=1,q='';
  var pg=document.createElement('div');pg.className='pager';
  var wrap=t.closest('.tablewrap')||t;wrap.parentNode.insertBefore(pg,wrap.nextSibling);
  function draw(){
    var m=rows.filter(function(r){return r.textContent.toLowerCase().indexOf(q)>-1});
    var pages=Math.max(1,Math.ceil(m.length/per));if(page>pages)page=pages;
    rows.forEach(function(r){r.hidden=true});
    m.slice((page-1)*per,page*per).forEach(function(r){r.hidden=false});
    pg.innerHTML='';
    if(pages>1)for(var i=1;i<=pages;i++){(function(i){var b=document.createElement('button');b.type='button';b.textContent=i;
      if(i===page)b.className='on';b.onclick=function(){page=i;draw()};pg.appendChild(b)})(i)}
  }
  if(gs)gs.addEventListener('input',function(){q=gs.value.toLowerCase();page=1;draw()});
  draw();
});
document.querySelectorAll('[data-rolesel]').forEach(function(s){
  function up(){document.querySelectorAll('.for-student').forEach(function(x){x.hidden=s.value!=='student'});
    document.querySelectorAll('.for-teacher').forEach(function(x){x.hidden=s.value!=='teacher'})}
  s.addEventListener('change',up);up();
});
window.mkChart=function(id,cfg){if(!window.Chart)return;
  Chart.defaults.color='#94A3B8';Chart.defaults.borderColor='#202B3D';Chart.defaults.font.family='Onest, sans-serif';
  return new Chart(document.getElementById(id),cfg)};
var toast=document.getElementById('timeout-toast');if(!toast)return;
var TOTAL=+D.timeout,WARN=+D.warn,left=TOTAL,last=Date.now();
var cd=document.getElementById('toast-count'),bar=document.getElementById('toast-bar'),clock=document.querySelectorAll('[data-session-clock]');
function fmt(s){var m=Math.floor(s/60),r=s%60;return m+':'+(r<10?'0':'')+r}
function ping(){last=Date.now();fetch(D.base+'ping.php',{credentials:'same-origin'}).then(function(r){if(r.status===401)location.href=D.base+'login.php?timeout=1'}).catch(function(){})}
setInterval(function(){left--;clock.forEach(function(c){c.textContent=fmt(Math.max(left,0))});
  if(left<=0){location.href=D.base+'logout.php?timeout=1';return}
  if(left<=WARN){toast.hidden=false;cd.textContent=left;bar.style.width=(left/WARN*100)+'%'}},1000);
['mousemove','keydown','click','scroll','touchstart'].forEach(function(ev){window.addEventListener(ev,function(){
  if(!toast.hidden)return;left=TOTAL;if(Date.now()-last>30000)ping()},{passive:true})});
document.getElementById('toast-extend').addEventListener('click',function(){left=TOTAL;toast.hidden=true;ping()});
})();
