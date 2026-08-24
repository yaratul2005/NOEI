<!-- Reusable Media Picker Modal Component for NOEI CMS -->
<div id="noei-media-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.6); z-index: 9999; justify-content: center; align-items: center; padding: 20px;">
    <div style="background: #ffffff; width: 100%; max-width: 800px; max-height: 85vh; border-radius: 10px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.2);">
        <div style="padding: 16px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.1rem;">Select Media Item</h3>
            <button type="button" onclick="closeNoeiMediaModal()" style="border: none; background: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <div id="noei-media-modal-body" style="padding: 20px; overflow-y: auto; flex-grow: 1; display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 14px;">
            <!-- Loaded dynamically via JavaScript -->
        </div>
        <div style="padding: 14px 24px; border-top: 1px solid #e2e8f0; text-align: right;">
            <button type="button" class="btn btn-secondary" onclick="closeNoeiMediaModal()">Cancel</button>
        </div>
    </div>
</div>

<script>
let onMediaSelectedCallback = null;

function openNoeiMediaModal(callback) {
    onMediaSelectedCallback = callback;
    const modal = document.getElementById('noei-media-modal');
    modal.style.display = 'flex';
    
    fetch('/api/v1/media')
        .then(res => res.json())
        .then(res => {
            const body = document.getElementById('noei-media-modal-body');
            body.innerHTML = '';
            if (res.status === 'success' && res.data.length > 0) {
                res.data.forEach(item => {
                    const meta = typeof item.meta_data === 'string' ? JSON.parse(item.meta_data || '{}') : (item.meta_data || {});
                    const isImg = item.mime_type.startsWith('image/');
                    const thumb = isImg && meta.variants && meta.variants.thumbnail ? '/' + meta.variants.thumbnail : '/' + item.file_path;
                    
                    const el = document.createElement('div');
                    el.style.cssText = 'border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; cursor: pointer; text-align: center; background: #fafafa;';
                    el.innerHTML = `<img src="${thumb}" style="height: 90px; width: 100%; object-fit: cover;"><div style="padding: 6px; font-size: 0.75rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${item.filename}</div>`;
                    el.onclick = () => {
                        if (onMediaSelectedCallback) {
                            onMediaSelectedCallback(item);
                        }
                        closeNoeiMediaModal();
                    };
                    body.appendChild(el);
                });
            } else {
                body.innerHTML = '<p style="grid-column: 1/-1; text-align: center; color: #64748b;">No media items found.</p>';
            }
        });
}

function closeNoeiMediaModal() {
    document.getElementById('noei-media-modal').style.display = 'none';
}
</script>
