document.addEventListener('DOMContentLoaded',function(){
  const form=document.querySelector('.ehb-search-form');
  if(!form) return;
  const ci=form.querySelector('input[name="ci"]');
  const co=form.querySelector('input[name="co"]');
  [ci,co].forEach(inp=>{
    if(!inp) return;
    ['focus','click'].forEach(evt=>{
      inp.addEventListener(evt,()=>{ if(inp.showPicker) inp.showPicker(); });
    });
  });
  if(ci && co){
    ci.addEventListener('change',()=>{
      if(ci.value){
        const d=new Date(ci.value);
        d.setDate(d.getDate()+1);
        co.value=d.toISOString().split('T')[0];
      }
    });
  }
});
