import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";

const AdminComments = () => {
  const [comments, setComments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [updating, setUpdating] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [refresh, setRefresh] = useState(0);
  const navigate = useNavigate();

  useEffect(() => {
    setLoading(true);
    setError('');
    fetch('/api/comments?per_page=30')
      .then(res => res.json())
      .then(data => {
        setComments(Array.isArray(data.data) ? data.data : []);
        setLoading(false);
      })
      .catch(() => {
        setError('Failed to load comments');
        setLoading(false);
      });
  }, [refresh]);

  const handleDelete = async (comment) => {
    if (!window.confirm('Are you sure you want to delete this comment?')) return;
    
    try {
      // First get CSRF cookie for session authentication
      await fetch('/sanctum/csrf-cookie', {
        credentials: 'include',
      });

      // Get XSRF token from cookie
      const xsrfToken = (() => {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : '';
      })();

      const response = await fetch(`/api/posts/${comment.post_id}/comments/${comment.id}`, {
        method: 'DELETE',
        headers: {
          'Accept': 'application/json',
          'X-XSRF-TOKEN': xsrfToken,
        },
        credentials: 'include',
      });

      const data = await response.json();
      if (!response.ok || !data.success) {
        alert(data.error || data.message || 'Failed to delete comment.');
      } else {
        setRefresh(r => r + 1);
      }
    } catch (error) {
      alert('Failed to delete comment.');
    }
  };

  const handleEdit = async (comment) => {
    // Create a better multi-line editing experience
    const modal = document.createElement('div');
    modal.style.cssText = `
      position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
      background: rgba(0,0,0,0.5); display: flex; align-items: center; 
      justify-content: center; z-index: 1000;
    `;
    
    const dialog = document.createElement('div');
    dialog.style.cssText = `
      background: white; padding: 20px; border-radius: 8px; 
      max-width: 600px; width: 90%; max-height: 80vh; overflow: auto;
    `;
    
    dialog.innerHTML = `
      <h3 style="margin-top: 0;">Edit Comment</h3>
              maxLength="250000"
        style={{ width: '100%', minHeight: '120px', padding: '8px', fontSize: '14px', fontFamily: 'inherit', lineHeight: '1.5', resize: 'vertical' }}
        defaultValue="${comment.content}"
      ></textarea>
      <div style="margin-top: 8px; font-size: 12px; color: #666;">
        ${comment.content.length}/250000 characters
      <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 16px;">
        <button id="cancel-btn" style="padding: 8px 16px; border: 1px solid #ccc; 
                background: white; border-radius: 4px; cursor: pointer;">Cancel</button>
        <button id="save-btn" style="padding: 8px 16px; border: none; 
                background: #3b82f6; color: white; border-radius: 4px; cursor: pointer;">Save</button>
      </div>
    `;
    
    modal.appendChild(dialog);
    document.body.appendChild(modal);
    
    const textarea = document.getElementById('edit-textarea');
    const charCount = document.getElementById('char-count');
    const saveBtn = document.getElementById('save-btn');
    const cancelBtn = document.getElementById('cancel-btn');
    
    textarea.focus();
    textarea.setSelectionRange(textarea.value.length, textarea.value.length);
    
    // Update character count
    textarea.addEventListener('input', () => {
      const length = textarea.value.length;
      charCount.textContent = `${length}/250000 characters`;
      charCount.style.color = length > 250000 ? '#dc2626' : '#666';
      saveBtn.disabled = length > 250000 || !textarea.value.trim();
    });
    
    // Handle save
    saveBtn.onclick = async () => {
      const newContent = textarea.value;
      if (!newContent.trim() || newContent === comment.content) {
        document.body.removeChild(modal);
        return;
      }
      
      if (newContent.length > 250000) {
        alert('Comment cannot exceed 250000 characters. Current length: ' + newContent.length);
        return;
      }
      
      try {
        await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
        const xsrfToken = (() => {
          const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
          return match ? decodeURIComponent(match[1]) : '';
        })();

        const response = await fetch(`/api/posts/${comment.post_id}/comments/${comment.id}`, {
          method: 'PATCH',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-XSRF-TOKEN': xsrfToken,
          },
          credentials: 'include',
          body: JSON.stringify({ content: newContent }),
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
          alert(data.error || data.message || 'Failed to update comment.');
        } else {
          setRefresh(r => r + 1);
          document.body.removeChild(modal);
        }
      } catch (error) {
        alert('Failed to update comment.');
      }
    };
    
    // Handle cancel
    cancelBtn.onclick = () => document.body.removeChild(modal);
    modal.onclick = (e) => {
      if (e.target === modal) document.body.removeChild(modal);
    };
  };

  return (
    <div style={{maxWidth:1100,margin:'40px auto',background:'#fff',padding:32,borderRadius:12,boxShadow:'0 2px 12px #e0e0e0'}}>
      <button onClick={() => navigate('/admin')} className="mb-4 text-blue-600 hover:underline">&larr; Back to Dashboard</button>
      <h2 className="text-xl font-bold mb-4">All Comments</h2>
      {loading ? <div>Loading...</div> : error ? <div style={{color:'#dc2626'}}>{error}</div> : (
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Content</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Post</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Updated</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {comments.map(comment => (
                <tr key={comment.id}>
                  <td className="px-6 py-4 whitespace-pre-line max-w-xs">
                    <div className="text-sm text-gray-900" style={{whiteSpace: 'pre-wrap', wordBreak: 'break-word'}}>{comment.content}</div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm text-gray-900">{comment.post?.title || `Post #${comment.post_id}`}</div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm text-gray-900">{comment.user?.name || comment.user?.email || 'Unknown'}</div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm text-gray-900">{comment.created_at ? new Date(comment.created_at).toLocaleString() : 'N/A'}</div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm text-gray-900">{comment.updated_at ? new Date(comment.updated_at).toLocaleString() : 'N/A'}</div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <button onClick={() => handleEdit(comment)} className="text-blue-600 hover:text-blue-900 mr-3">Edit</button>
                    <button onClick={() => handleDelete(comment)} className="text-red-600 hover:text-red-900">Delete</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
};

export default AdminComments;
