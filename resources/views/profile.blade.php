@extends('layouts.app')

@section('title', 'My Profile Settings - LiveTV BD')

@section('content')
<div class="movies-explorer">
    <h2 class="section-title mb-4">Profile Settings</h2>
    
    <div class="profile-card">
        <form id="profile-form" onsubmit="saveUserProfileData(event)">
            <!-- Avatar Selector -->
            <div class="form-group">
                <label>Choose Avatar</label>
                <div class="avatar-selector">
                    <div class="avatar-option selected" data-avatar="{{ asset('images/profile.jpg') }}" onclick="selectAvatar(this)">
                        <img src="{{ asset('images/profile.jpg') }}" alt="Engr Saad avatar">
                    </div>
                    <div class="avatar-option" data-avatar="https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=150" onclick="selectAvatar(this)">
                        <img src="https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=150" alt="Boy avatar">
                    </div>
                    <div class="avatar-option" data-avatar="https://images.unsplash.com/photo-1570295999919-56ceb5ecca61?w=150" onclick="selectAvatar(this)">
                        <img src="https://images.unsplash.com/photo-1570295999919-56ceb5ecca61?w=150" alt="Man avatar">
                    </div>
                    <div class="avatar-option" data-avatar="https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=150" onclick="selectAvatar(this)">
                        <img src="https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=150" alt="Girl avatar">
                    </div>
                </div>
            </div>

            <!-- Username Field -->
            <div class="form-group">
                <label for="username-input">Name / Profile Name</label>
                <input type="text" id="username-input" required placeholder="Enter your name">
            </div>

            <!-- Phone Field -->
            <div class="form-group">
                <label for="phone-input">Phone Number</label>
                <input type="text" id="phone-input" placeholder="Enter your phone number">
            </div>

            <!-- Preferred Resolution Field -->
            <div class="form-group">
                <label for="resolution-select">Preferred Streaming Quality</label>
                <select id="resolution-select">
                    <option value="auto">Auto / Adaptive (Recommended)</option>
                    <option value="1080">1080p Full HD</option>
                    <option value="720">720p HD</option>
                    <option value="480">Standard Definition (480p)</option>
                </select>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn-primary w-100 justify-content-center mt-3">
                <i class="bi bi-save"></i> Save Profile
            </button>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    let selectedAvatarUrl = "{{ asset('images/profile.jpg') }}";

    document.addEventListener('DOMContentLoaded', () => {
        // Load existing data
        const username = localStorage.getItem('user_name') || 'Engr Saad';
        const phone = localStorage.getItem('user_phone') || '';
        const avatar = localStorage.getItem('user_avatar') || "{{ asset('images/profile.jpg') }}";
        const quality = localStorage.getItem('user_streaming_quality') || 'auto';

        document.getElementById('username-input').value = username;
        document.getElementById('phone-input').value = phone;
        document.getElementById('resolution-select').value = quality;
        selectedAvatarUrl = avatar;

        // Select the active avatar in the grid
        const options = document.querySelectorAll('.avatar-option');
        options.forEach(opt => {
            opt.classList.remove('selected');
            if (opt.dataset.avatar === avatar) {
                opt.classList.add('selected');
            }
        });
    });

    function selectAvatar(element) {
        const options = document.querySelectorAll('.avatar-option');
        options.forEach(opt => opt.classList.remove('selected'));
        element.classList.add('selected');
        selectedAvatarUrl = element.dataset.avatar;
    }

    function saveUserProfileData(event) {
        event.preventDefault();
        const username = document.getElementById('username-input').value.trim();
        const phone = document.getElementById('phone-input').value.trim();
        const quality = document.getElementById('resolution-select').value;

        localStorage.setItem('user_name', username);
        localStorage.setItem('user_phone', phone);
        localStorage.setItem('user_avatar', selectedAvatarUrl);
        localStorage.setItem('user_streaming_quality', quality);

        // Update layouts header state immediately
        loadUserProfile();

        showToast('Profile settings saved successfully');
    }
</script>
@endsection
