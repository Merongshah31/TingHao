@csrf

<div class="form-grid">
    <label>
        <span>Supplier Name</span>
        <input name="name" value="{{ old('name', $supplier->name) }}" type="text" required>
        @error('name') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Contact Person</span>
        <input name="contact_person" value="{{ old('contact_person', $supplier->contact_person) }}" type="text">
        @error('contact_person') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Phone</span>
        <input name="phone" value="{{ old('phone', $supplier->phone) }}" type="text">
        @error('phone') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Email</span>
        <input name="email" value="{{ old('email', $supplier->email) }}" type="email">
        @error('email') <small>{{ $message }}</small> @enderror
    </label>

    <label class="form-wide">
        <span>Address</span>
        <textarea name="address" rows="3">{{ old('address', $supplier->address) }}</textarea>
        @error('address') <small>{{ $message }}</small> @enderror
    </label>

    <label class="form-wide">
        <span>Notes</span>
        <textarea name="notes" rows="4">{{ old('notes', $supplier->notes) }}</textarea>
        @error('notes') <small>{{ $message }}</small> @enderror
    </label>
</div>

<div class="form-actions">
    <a href="{{ $supplier->exists ? route('suppliers.show', $supplier) : route('suppliers.index') }}" class="btn btn-muted">Cancel</a>
    <button type="submit" class="btn btn-primary">{{ $buttonLabel }}</button>
</div>
