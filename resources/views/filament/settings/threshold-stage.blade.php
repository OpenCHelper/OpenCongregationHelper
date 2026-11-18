{{-- A stage without a threshold of its own, among the fields of the thresholds (see `AppSettingsPage::stageRow()`) --}}
<div class="fi-settings-stage fi-settings-stage-static" style="--stage-color: {{ $color }}">
    <span class="fi-settings-stage-name">
        <span class="fi-settings-stage-dot"></span>{{ $name }}
    </span>
    <span class="fi-settings-stage-note">{{ $note }}</span>
</div>
