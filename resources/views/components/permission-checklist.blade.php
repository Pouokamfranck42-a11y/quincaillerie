@props(['selected' => []])

<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:16px">
    @foreach (config('permissions') as $module => $group)
        <div class="card" style="margin:0">
            <div style="font-weight:600; margin-bottom:8px">{{ $group['label'] }}</div>
            @foreach ($group['permissions'] as $name => $label)
                <label style="display:flex; align-items:flex-start; gap:8px; font-weight:400; margin-bottom:6px; cursor:pointer">
                    <input type="checkbox" name="permissions[]" value="{{ $name }}" @checked(in_array($name, old('permissions', $selected)))>
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
    @endforeach
</div>
