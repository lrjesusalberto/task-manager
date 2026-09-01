const API = '/api';

const el = (id) => document.getElementById(id);

const estado = {
  categorias: [],
  editando: null,
};

/* ---------- Cliente de la API ---------- */

async function pedir(ruta, opciones = {}) {
  const respuesta = await fetch(`${API}${ruta}`, {
    headers: { 'Content-Type': 'application/json' },
    ...opciones,
  });

  if (respuesta.status === 204) {
    return null;
  }

  const datos = await respuesta.json().catch(() => ({}));

  if (!respuesta.ok) {
    const error = new Error(datos.error ?? 'Error en la petición');
    error.detalles = datos.detalles ?? {};
    throw error;
  }

  return datos;
}

/* ---------- Utilidades ---------- */

function formatearFecha(iso) {
  if (!iso) return '';
  return new Date(`${iso}T12:00:00`).toLocaleDateString('es-ES', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
}

function estaVencida(tarea) {
  if (!tarea.vence_el || tarea.completada) return false;
  return tarea.vence_el < new Date().toISOString().slice(0, 10);
}

function limpiarErrores() {
  document.querySelectorAll('.campo__error').forEach((p) => {
    p.textContent = '';
  });
}

function mostrarErrores(detalles) {
  limpiarErrores();
  for (const [campo, mensaje] of Object.entries(detalles)) {
    const destino = el(`error-${campo}`);
    if (destino) destino.textContent = mensaje;
  }
}

/* ---------- Renderizado ---------- */

function pintarResumen({ total, completadas, pendientes }) {
  el('resumen').textContent =
    total === 0
      ? 'Sin tareas todavía'
      : `${total} tareas · ${pendientes} pendientes · ${completadas} completadas`;
}

function pintarTareas(tareas) {
  const lista = el('lista');

  if (tareas.length === 0) {
    lista.innerHTML =
      '<p class="vacio">No hay tareas que coincidan con los filtros seleccionados.</p>';
    return;
  }

  lista.innerHTML = tareas
    .map((t) => {
      const vencida = estaVencida(t);
      return `
        <article class="tarea ${t.completada ? 'tarea--hecha' : ''}" data-id="${t.id}">
          <label class="tarea__marca">
            <input type="checkbox" ${t.completada ? 'checked' : ''}
                   data-accion="alternar" data-id="${t.id}"
                   aria-label="Marcar «${escapar(t.titulo)}» como completada" />
          </label>

          <div class="tarea__cuerpo">
            <h3 class="tarea__titulo">${escapar(t.titulo)}</h3>
            ${t.descripcion ? `<p class="tarea__descripcion">${escapar(t.descripcion)}</p>` : ''}

            <p class="tarea__meta">
              <span class="etiqueta etiqueta--${t.prioridad}">${t.prioridad}</span>
              ${
                t.categoria_nombre
                  ? `<span class="etiqueta etiqueta--categoria"
                       style="border-color:${t.categoria_color}">${escapar(t.categoria_nombre)}</span>`
                  : ''
              }
              ${
                t.vence_el
                  ? `<span class="${vencida ? 'vence vence--pasada' : 'vence'}">
                       ${vencida ? 'Venció el' : 'Vence el'} ${formatearFecha(t.vence_el)}
                     </span>`
                  : ''
              }
            </p>
          </div>

          <div class="tarea__acciones">
            <button class="enlace" data-accion="editar" data-id="${t.id}">Editar</button>
            <button class="enlace enlace--peligro" data-accion="borrar" data-id="${t.id}">
              Eliminar
            </button>
          </div>
        </article>`;
    })
    .join('');
}

function pintarCategorias() {
  const lista = el('lista-categorias');

  lista.innerHTML =
    estado.categorias.length === 0
      ? '<li class="vacio">Todavía no hay categorías.</li>'
      : estado.categorias
          .map(
            (c) => `
        <li class="categoria">
          <span class="categoria__punto" style="background:${c.color}"></span>
          <span class="categoria__nombre">${escapar(c.nombre)}</span>
          <span class="categoria__total">${c.total_tareas} ${
            c.total_tareas === 1 ? 'tarea' : 'tareas'
          }</span>
          <button class="enlace enlace--peligro" data-accion="borrar-categoria" data-id="${c.id}">
            Eliminar
          </button>
        </li>`,
          )
          .join('');

  // Rellena los dos selectores de categoría manteniendo la selección.
  for (const [id, textoVacio] of [
    ['categoria', 'Sin categoría'],
    ['filtro-categoria', 'Todas'],
  ]) {
    const select = el(id);
    const anterior = select.value;

    select.innerHTML =
      `<option value="">${textoVacio}</option>` +
      estado.categorias
        .map((c) => `<option value="${c.id}">${escapar(c.nombre)}</option>`)
        .join('');

    select.value = anterior;
  }
}

/** Evita inyección de HTML al pintar texto que viene del usuario. */
function escapar(texto) {
  const div = document.createElement('div');
  div.textContent = texto ?? '';
  return div.innerHTML;
}

/* ---------- Carga de datos ---------- */

async function cargarTareas() {
  const parametros = new URLSearchParams();
  const estadoSel = el('filtro-estado').value;
  const prioridad = el('filtro-prioridad').value;
  const categoria = el('filtro-categoria').value;
  const buscar = el('filtro-buscar').value.trim();

  if (estadoSel !== 'todas') parametros.set('estado', estadoSel);
  if (prioridad) parametros.set('prioridad', prioridad);
  if (categoria) parametros.set('categoria_id', categoria);
  if (buscar) parametros.set('buscar', buscar);

  try {
    const { tareas, resumen } = await pedir(`/tareas?${parametros}`);
    pintarTareas(tareas);
    pintarResumen(resumen);
  } catch (error) {
    el('lista').innerHTML = `<p class="vacio">${escapar(error.message)}</p>`;
  }
}

async function cargarCategorias() {
  try {
    estado.categorias = await pedir('/categorias');
    pintarCategorias();
  } catch {
    estado.categorias = [];
  }
}

/* ---------- Formulario de tareas ---------- */

function entrarEnEdicion(tarea) {
  estado.editando = tarea.id;
  el('tarea-id').value = tarea.id;
  el('titulo').value = tarea.titulo;
  el('descripcion').value = tarea.descripcion ?? '';
  el('prioridad').value = tarea.prioridad;
  el('categoria').value = tarea.categoria_id ?? '';
  el('vence').value = tarea.vence_el ?? '';
  el('btn-guardar').textContent = 'Guardar cambios';
  el('btn-cancelar').hidden = false;
  el('titulo').focus();
}

function salirDeEdicion() {
  estado.editando = null;
  el('form-tarea').reset();
  el('tarea-id').value = '';
  el('prioridad').value = 'media';
  el('btn-guardar').textContent = 'Añadir tarea';
  el('btn-cancelar').hidden = true;
  limpiarErrores();
}

el('form-tarea').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  limpiarErrores();

  const cuerpo = {
    titulo: el('titulo').value.trim(),
    descripcion: el('descripcion').value.trim() || null,
    prioridad: el('prioridad').value,
    categoria_id: el('categoria').value ? Number(el('categoria').value) : null,
    vence_el: el('vence').value || null,
  };

  try {
    if (estado.editando) {
      await pedir(`/tareas/${estado.editando}`, {
        method: 'PUT',
        body: JSON.stringify(cuerpo),
      });
    } else {
      await pedir('/tareas', { method: 'POST', body: JSON.stringify(cuerpo) });
    }

    salirDeEdicion();
    await Promise.all([cargarTareas(), cargarCategorias()]);
  } catch (error) {
    mostrarErrores(error.detalles);
    if (Object.keys(error.detalles ?? {}).length === 0) {
      alert(error.message);
    }
  }
});

el('btn-cancelar').addEventListener('click', salirDeEdicion);

/* ---------- Acciones sobre la lista ---------- */

el('lista').addEventListener('click', async (evento) => {
  const boton = evento.target.closest('[data-accion]');
  if (!boton) return;

  const { accion, id } = boton.dataset;

  if (accion === 'alternar') {
    await pedir(`/tareas/${id}/completar`, { method: 'PATCH' });
    await cargarTareas();
    return;
  }

  if (accion === 'editar') {
    entrarEnEdicion(await pedir(`/tareas/${id}`));
    return;
  }

  if (accion === 'borrar') {
    if (!confirm('¿Eliminar esta tarea?')) return;
    await pedir(`/tareas/${id}`, { method: 'DELETE' });
    if (estado.editando === Number(id)) salirDeEdicion();
    await Promise.all([cargarTareas(), cargarCategorias()]);
  }
});

/* ---------- Categorías ---------- */

el('form-categoria').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  limpiarErrores();

  try {
    await pedir('/categorias', {
      method: 'POST',
      body: JSON.stringify({
        nombre: el('cat-nombre').value.trim(),
        color: el('cat-color').value,
      }),
    });

    el('cat-nombre').value = '';
    await cargarCategorias();
  } catch (error) {
    mostrarErrores(error.detalles);
  }
});

el('lista-categorias').addEventListener('click', async (evento) => {
  const boton = evento.target.closest('[data-accion="borrar-categoria"]');
  if (!boton) return;

  if (!confirm('¿Eliminar la categoría? Sus tareas quedarán sin categoría.')) return;

  await pedir(`/categorias/${boton.dataset.id}`, { method: 'DELETE' });
  await Promise.all([cargarCategorias(), cargarTareas()]);
});

/* ---------- Filtros ---------- */

let temporizador;

for (const id of ['filtro-estado', 'filtro-prioridad', 'filtro-categoria']) {
  el(id).addEventListener('change', cargarTareas);
}

el('filtro-buscar').addEventListener('input', () => {
  clearTimeout(temporizador);
  temporizador = setTimeout(cargarTareas, 300);
});

/* ---------- Arranque ---------- */

await cargarCategorias();
await cargarTareas();
