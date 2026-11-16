// HTML templates live in ./templates as real .html files. Vite imports them as
// raw text; each one is parsed exactly once here, and the app clones the parsed
// result whenever it needs a fresh copy.
import shellHTML from './templates/shell.html?raw';
import dashboardHTML from './templates/dashboard.html?raw';
import projectsHTML from './templates/projects.html?raw';
import boardHTML from './templates/project-board.html?raw';
import projectFormHTML from './templates/project-form.html?raw';
import taskFormHTML from './templates/task-form.html?raw';
import projectCardHTML from './templates/project-card.html?raw';
import projectListingHTML from './templates/project-listing.html?raw';
import taskCardHTML from './templates/task-card.html?raw';
import laneHTML from './templates/lane.html?raw';

function parse(html) {
  const template = document.createElement('template');
  template.innerHTML = html.trim();
  return template;
}

// A page section (dashboard, forms, ...): returns a fresh DocumentFragment.
function fragment(html) {
  const template = parse(html);
  return () => template.content.cloneNode(true);
}

// A file wrapping a single <template> (cards, lanes): returns a fresh element.
function element(html) {
  const inner = parse(html).content.querySelector('template');
  return () => inner.content.firstElementChild.cloneNode(true);
}

export const templates = {
  shell: fragment(shellHTML),
  dashboard: fragment(dashboardHTML),
  projects: fragment(projectsHTML),
  board: fragment(boardHTML),
  projectForm: fragment(projectFormHTML),
  taskForm: fragment(taskFormHTML),
  projectCard: element(projectCardHTML),
  projectListing: element(projectListingHTML),
  taskCard: element(taskCardHTML),
  lane: element(laneHTML),
};
