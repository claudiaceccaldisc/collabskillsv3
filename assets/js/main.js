// assets/js/main.js

// ---------- Kanban Drag & Drop ----------
function onDragStart(ev, taskId){
  ev.dataTransfer.setData("text/plain", taskId);
}

function onDragOver(ev){
  ev.preventDefault(); // autorise le drop
}

function onDrop(ev, newStatus){
  ev.preventDefault();
  const taskId = ev.dataTransfer.getData("text/plain");
  
  // Appel AJAX pour mettre à jour le status
  fetch('ajax_update_task.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `task_id=${taskId}&new_status=${newStatus}`
  })
  .then(resp => resp.text())
  .then(data => {
    if(data.trim() === "OK"){
      // Reload la page ou déplacer l'élément par JS
      location.reload();
    } else {
      alert("Erreur mise à jour : " + data);
    }
  });
}

// ---------- Chat & Pusher ----------
// assets/js/main.js

// Kanban : déjà inclus plus haut

// Pusher Notifications en direct (ex. sur TOUTES les pages loguées)
document.addEventListener('DOMContentLoaded', () => {
  if(typeof Pusher !== 'undefined'){
    const pusher = new Pusher('7c78e787d24818138f9d', { cluster: 'eu', encrypted: true });
    const channel = pusher.subscribe('my-channel');

    channel.bind('notification', function(data){
      // On reçoit {user_id, message, type}
      // Vérif si l'user_id correspond à l'utilisateur actuel ?
      // Si on n'a pas l'user_id du front, on peut le stocker en data-attribute quelque part ou en session
      // On affiche un mini toast
      if(typeof CURRENT_USER_ID !== 'undefined' && data.user_id == CURRENT_USER_ID){
        alert("Notification : " + data.message);
        // ou on peut faire un joli toast bootstrap
      }
    });
  }
});
