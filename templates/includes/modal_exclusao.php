<!-- Modal Reutilizável para Exclusão -->
<div id="modal-exclusao" class="modal" style="display: none;">
  <div class="modal-content">
    <h3 id="titulo-modal">Justificativa para exclusão</h3>
    <form id="formExclusao">
      <input type="hidden" name="id" id="excluir-id">
      <textarea name="justificativa" id="justificativa" required placeholder="Descreva o motivo da exclusão..." rows="4" style="width: 100%;"></textarea>
      <div class="modal-actions">
        <button type="button" onclick="fecharModal()">Cancelar</button>
        <button type="submit" class="btn-perigo">Confirmar Exclusão</button>
      </div>
    </form>
  </div>
</div>

<style>
  .modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
  }

  .modal-content {
    background: #fff;
    padding: 2rem;
    border-radius: 8px;
    width: 100%;
    max-width: 500px;
  }

  .modal-actions {
    margin-top: 1rem;
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
  }

  .btn-perigo {
    background-color: #e53935;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    cursor: pointer;
  }
</style>

<script>
  let endpointExclusao = ''; // endpoint que será usado na requisição

  function abrirModalExclusao(id, endpoint, titulo = 'Justificativa para exclusão') {
    document.getElementById('excluir-id').value = id;
    document.getElementById('justificativa').value = '';
    document.getElementById('titulo-modal').innerText = titulo;
    endpointExclusao = endpoint;
    document.getElementById('modal-exclusao').style.display = 'flex';
  }

  function fecharModal() {
    document.getElementById('modal-exclusao').style.display = 'none';
  }

  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('formExclusao');
    form.addEventListener('submit', function(e) {
      e.preventDefault();

      const id = document.getElementById('excluir-id').value;
      const justificativa = document.getElementById('justificativa').value;

      if (!justificativa.trim()) {
        alert("Informe a justificativa.");
        return;
      }

      fetch(`${endpointExclusao}?id=${id}`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: `justificativa=${encodeURIComponent(justificativa)}`
        })
        .then(res => res.text())
        .then(msg => {
          alert(msg);
          window.location.reload();
        })
        .catch(err => {
          alert("Erro ao excluir.");
          console.error(err);
        });
    });
  });
</script>
