{{-- Details form fields, shared by "add colleague" and the profile. --}}
<div class="form-grid">
    <label class="field"><span>{{ __('users.fields.first_name') }}</span>
        <input name="first_name" value="{{ old('first_name', $user->first_name) }}" required maxlength="80" autocomplete="off"></label>
    <label class="field"><span>{{ __('users.fields.last_name') }}</span>
        <input name="last_name" value="{{ old('last_name', $user->last_name) }}" required maxlength="80" autocomplete="off"></label>
    <label class="field span-2"><span>{{ __('users.fields.email') }}</span>
        <input type="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="190" autocomplete="off">
        <small>{{ __('users.hints.email') }}</small></label>
    <label class="field"><span>{{ __('users.fields.title') }}</span>
        <input name="title" value="{{ old('title', $user->title) }}" maxlength="120"></label>
    <label class="field"><span>{{ __('users.fields.department') }}</span>
        <input name="department" value="{{ old('department', $user->department) }}" maxlength="120"></label>
    <label class="field"><span>{{ __('users.fields.role_id') }}</span>
        <select name="role_id" required>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected((string) old('role_id', $user->role_id) === (string) $role->id)>{{ $role->name }}</option>
            @endforeach
        </select>
        <small>{{ __('users.hints.role') }}</small></label>
    <label class="field"><span>{{ __('users.fields.reports_to_id') }}</span>
        <select name="reports_to_id">
            <option value="">{{ __('users.fields.nobody') }}</option>
            @foreach ($managers as $m)
                <option value="{{ $m->id }}" @selected((string) old('reports_to_id', $user->reports_to_id) === (string) $m->id)>{{ $m->name }}{{ $m->title ? ' · '.$m->title : '' }}</option>
            @endforeach
        </select>
        <small>{{ __('users.hints.reports_to') }}</small></label>
    <label class="field"><span>{{ __('users.fields.phone') }}</span>
        <input name="phone" value="{{ old('phone', $user->phone) }}" maxlength="40" inputmode="tel"></label>
    <label class="field"><span>{{ __('users.fields.mobile') }}</span>
        <input name="mobile" value="{{ old('mobile', $user->mobile) }}" maxlength="40" inputmode="tel"></label>
    <label class="field"><span>{{ __('users.fields.location') }}</span>
        <input name="location" value="{{ old('location', $user->location) }}" maxlength="120"></label>
    <label class="field"><span>{{ __('users.fields.locale') }}</span>
        <select name="locale">
            @foreach (config('cluk.locales') as $code => $name)
                <option value="{{ $code }}" @selected(old('locale', $user->locale) === $code)>{{ $name }}</option>
            @endforeach
        </select></label>
    <label class="check span-2">
        <input type="checkbox" name="is_commercial" value="1" @checked(old('is_commercial', $user->is_commercial))>
        <span>{{ __('users.fields.is_commercial') }}</span></label>
    <label class="check span-2">
        <input type="checkbox" name="email_login" value="1" @checked(old('email_login', $user->email_login))>
        <span>{{ __('users.fields.email_login', ['domain' => config('cluk.staff_email_domain')]) }}<br><small class="muted">{{ __('users.fields.email_login_hint') }}</small></span></label>
    @error('email_login')<small class="txt-danger span-2">{{ $message }}</small>@enderror
    <label class="field span-2"><span>{{ __('users.fields.code_email') }}</span>
        <input type="email" name="code_email" maxlength="190" value="{{ old('code_email', $user->code_email) }}" placeholder="name@gmail.com">
        <small class="muted">{{ __('users.fields.code_email_hint') }}</small>
        @error('code_email')<small class="txt-danger">{{ $message }}</small>@enderror</label>
</div>
