document.addEventListener('DOMContentLoaded',function(){
  var sidebar=document.querySelector('.rsv-admin-sidebar');
  var toggle=document.querySelector('.rsv-admin-menu-toggle');
  var links=sidebar?sidebar.querySelectorAll('nav a'):[];
  var sections=document.querySelectorAll('.rsv-admin-section');
  if(toggle){toggle.addEventListener('click',function(){sidebar.classList.toggle('open');});}
  links.forEach(function(l){
    l.addEventListener('click',function(e){
      e.preventDefault();
      var target=this.getAttribute('data-section');
      sections.forEach(function(s){s.style.display=s.id==='rsv-section-'+target?'block':'none';});
      links.forEach(function(a){a.classList.remove('active');});
      this.classList.add('active');
      if(sidebar.classList.contains('open')) sidebar.classList.remove('open');
    });
  });
  var galleryInput=document.getElementById('rsv-gallery-input');
  var preview=document.getElementById('rsv-gallery-preview');
  if(galleryInput&&preview){
    galleryInput.addEventListener('change',function(){
      preview.innerHTML='';
      Array.prototype.forEach.call(galleryInput.files,function(file){
        if(!file.type.match('image')) return;
        var reader=new FileReader();
        reader.onload=function(e){
          var img=document.createElement('img');
          img.src=e.target.result;
          preview.appendChild(img);
        };
        reader.readAsDataURL(file);
      });
    });
  }
});
