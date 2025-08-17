(function(){
  document.addEventListener('DOMContentLoaded', function(){
    var el = document.getElementById('rsv-calendar');
    if(!el || typeof FullCalendar === 'undefined') return;
    var calendar = new FullCalendar.Calendar(el, {
      initialView: 'dayGridMonth',
      height: 'auto',
      events: function(info, success){
        fetch(RSV_SINGLE.ajax_url+'?action=rsv_get_booked&type_id='+RSV_SINGLE.id+'&nonce='+RSV_SINGLE.nonce)
          .then(function(r){ return r.json(); })
          .then(function(res){ success(res && res.success ? res.data : []); })
          .catch(function(){ success([]); });
      },
      eventColor: '#ff5a5f',
      displayEventTime: false
    });
    calendar.render();
  });
})();
