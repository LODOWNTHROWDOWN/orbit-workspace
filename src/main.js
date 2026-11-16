import './style.scss';
import {request, initialize} from './api.js';
import {templates} from './templates.js';

const app = document.querySelector('#app');
const state = {projects: [], tasks: [], activeProject: null, view: 'dashboard'};
const $ = (selector, root = document) => root.querySelector(selector);

function emptyMessage(text, className = 'lane-empty') {
  const el = document.createElement('p');
  el.className = className;
  el.textContent = text;
  return el;
}
function shell() {
  app.replaceChildren(templates.shell());
  $('#nav-dashboard').addEventListener('click', () => {state.view = 'dashboard'; render();});
  $('#nav-projects').addEventListener('click', () => {state.view = 'projects'; render();});
  $('#close-dialog').addEventListener('click', () => $('#editor').close());
  $('#cancel-dialog').addEventListener('click', () => $('#editor').close());
}
let toastTimeout;
function notice(message) {
  const el = $('#toast');
  el.textContent = message;
  el.hidden = false;
  clearTimeout(toastTimeout);
  toastTimeout = setTimeout(() => {el.hidden = true;}, 4500);
}
function error(e) {notice(e.message || 'Something went wrong. Please retry.');}
async function load() {
  const data = await request('/projects');
  state.projects = data.projects;
  if (state.activeProject && !state.projects.some(p => p.id === state.activeProject)) state.activeProject = null;
  if (state.activeProject) {
    const result = await request(`/projects/${state.activeProject}/tasks`);
    state.tasks = result.tasks;
  } else state.tasks = [];
  render();
}
// Project cards (Projects page) and listings (dashboard) share the same data and behavior.
function buildProjectEntry(createElement, project) {
  const entry = createElement();
  entry.dataset.project = String(project.id);
  $('[data-field="name"]', entry).textContent = project.name;
  $('[data-field="description"]', entry).textContent = project.description || 'No description yet';
  entry.addEventListener('click', async () => {
    state.activeProject = Number(project.id);
    state.view = 'projects';
    try {await load();} catch (e) {error(e);}
  });
  return entry;
}
function populateProjects() {
  const grid = $('#project-grid');
  const list = $('#project-list');
  if (!state.projects.length) {
    if (grid) {
      grid.append(
        emptyMessage('No projects yet. Create your first one.', 'empty-projects')
      );
    }

    if (list) {
      list.append(
        emptyMessage('No projects yet. Create your first one.', 'empty-projects')
      );
    }
    return;
  }
  if (grid) {
    state.projects.forEach(project => {
      grid.append(buildProjectEntry(templates.projectCard, project));
    });
  }

  if (list) {
    state.projects.forEach(project => {
      list.append(buildProjectEntry(templates.projectListing, project));
    });
  }
}
function render() {
  document.querySelectorAll('.nav-link').forEach(el => {
    const active = el.id === `nav-${state.view === 'dashboard' ? 'dashboard' : 'projects'}`;
    el.classList.toggle('active', active);
    if (active) el.setAttribute('aria-current', 'page');
    else el.removeAttribute('aria-current');
  });
  const main = $('#main');
  if (state.view === 'dashboard') {
    main.replaceChildren(templates.dashboard());
    $('#project-count').textContent = String(state.projects.length);
    $('#current-project-count').textContent = state.activeProject ? '1' : '—';
    //$('#projects-total').textContent = `${state.projects.length} total`;
    populateProjects();
  } else if (!state.activeProject) {
    main.replaceChildren(templates.projects());
    populateProjects();
  } else {
    const project = state.projects.find(p => p.id === state.activeProject);
    main.replaceChildren(templates.board());
    $('#board-project-name').textContent = project?.name || 'Project';
    $('#board-project-description').textContent = project?.description || 'Manage this project’s tasks.';
    $('#back-projects').addEventListener('click', () => {state.activeProject = null; state.view = 'projects'; render();});
    $('#new-task').addEventListener('click', openTaskEditor);
    $('#filter').addEventListener('input', drawBoard);
    $('#priority-filter').addEventListener('change', drawBoard);
    drawBoard();
  }
  $('#new-project')?.addEventListener('click', openProjectEditor);
}
function buildTaskCard(task) {
  const card = templates.taskCard();
  $('[data-field="title"]', card).textContent = task.title;
  $('[data-field="description"]', card).textContent = task.description || 'No description';
  const priority = $('[data-field="priority"]', card);
  priority.textContent = task.priority;
  priority.classList.add(['high', 'medium', 'low'].includes(task.priority) ? task.priority : 'medium');
  if (task.due_date) {
    const dueDate = $('[data-field="due-date"]', card);
    dueDate.hidden = false;
    dueDate.dateTime = task.due_date;
    dueDate.textContent = task.due_date;
  }
  const select = $('[data-task-status]', card);
  const statusId = `task-status-${task.id}`;
  select.id = statusId;
  select.dataset.taskStatus = String(task.id);
  select.value = task.status;
  $('[data-field="status-label"]', card).htmlFor = statusId;
  select.addEventListener('change', async () => {
    const newStatus = select.value;
    select.disabled = true;
    try {
      await request(`/tasks/${task.id}`, {method: 'PATCH', body: {status: newStatus}});
      await load();
      notice('Task status updated');
    } catch (e) {
      select.value = task.status;
      select.disabled = false;
      error(e);
    }
  });
  return card;
}
function drawBoard() {
  const host = $('#board');
  if (!host) return;
  const term = $('#filter').value.toLowerCase().trim();
  const priority = $('#priority-filter').value;
  const filtered = state.tasks.filter(t =>
    (!term || t.title.toLowerCase().includes(term)) && (!priority || t.priority === priority)
  );
  const statuses = [['todo', 'To Do'], ['progress', 'In Progress'], ['done', 'Done']];
  host.replaceChildren();
  statuses.forEach(([value, label]) => {
    const lane = templates.lane();
    lane.setAttribute('aria-label', label);
    $('[data-field="name"]', lane).textContent = label;
    const tasks = filtered.filter(t => t.status === value);
    $('[data-field="count"]', lane).textContent = String(tasks.length);
    const slot = $('[data-slot="tasks"]', lane);
    if (!tasks.length) slot.append(emptyMessage('No tasks'));
    else tasks.forEach(t => slot.append(buildTaskCard(t)));
    host.append(lane);
  });
}
function openEditor(title, fields, onSubmit) {
  const dialog = $('#editor');
  $('#dialog-title').textContent = title;
  $('#fields').replaceChildren(fields);
  const form = $('#editor-form');
  form.reset();
  form.onsubmit = async e => {
    e.preventDefault();
    const submitButton = form.querySelector('button[type="submit"]');
    submitButton.disabled = true;
    try {
      await onSubmit(new FormData(form));
      dialog.close();
      await load();
    } catch (err) {error(err);}
    finally {submitButton.disabled = false;}
  };
  dialog.showModal();
  $('#fields input')?.focus();
}
function openProjectEditor() {
  openEditor('New project', templates.projectForm(), async form => {
    await request('/projects', {method: 'POST', body: {name: form.get('name'), description: form.get('description')}});
    notice('Project created');
  });
}
function openTaskEditor() {
  openEditor('New task', templates.taskForm(), async form => {
    await request(`/projects/${state.activeProject}/tasks`, {method: 'POST', body: {
      title: form.get('title'), description: form.get('description'), priority: form.get('priority'), due_date: form.get('due_date') || null
    }});
    notice('Task created');
  });
}
async function start() {
  shell();
  $('#main').replaceChildren(emptyMessage('Loading workspace…', 'loading'));
  try {await initialize(); await load();}
  catch (e) {
    $('#main').innerHTML = '<h1>Unable to load workspace</h1><p>Check that the PHP API and database are running, then refresh.</p>';
    error(e);
  }
}
start();
