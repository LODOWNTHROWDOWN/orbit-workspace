let csrf = '';
export async function request(path, {method='GET',body}={}) {
  const response = await fetch('/api'+path, {method,credentials:'same-origin',headers:{...(body?{'Content-Type':'application/json'}:{}),...(method!=='GET'?{'X-CSRF-Token':csrf}:{})},body:body?JSON.stringify(body):undefined});
  const data = await response.json().catch(()=>({error:'Server returned an unexpected response.'}));
  if (!response.ok) throw new Error(data.error || `Request failed (${response.status})`);
  return data;
}
export async function initialize() { const data=await request('/bootstrap'); csrf=data.csrfToken; return data; }
