{{--
    The stages of a sequence of thresholds drawn to scale, updated while the thresholds are typed (see
    `AppSettingsPage::stagesBar()`). The open-ended stages (without a value bounding them) get a fixed width.
--}}
<div
    x-data="{
        stages: @js($stages),
        isCountdown: @js($isCountdown),
        openWidth: 16,
        value(field) {
            const value = parseInt($wire.data?.[field] ?? '', 10)

            return Number.isNaN(value) ? 0 : Math.max(value, 0)
        },
        spans() {
            return this.stages.map((stage, index) => {
                if (this.isCountdown) {
                    if (! stage.field) {
                        return null
                    }

                    const next = this.stages.slice(index + 1).find((other) => other.field)

                    return Math.max(this.value(stage.field) - (next ? this.value(next.field) : 0), 0)
                }

                const next = this.stages[index + 1]

                if (! next) {
                    return null
                }

                return Math.max(this.value(next.field) - (stage.field ? this.value(stage.field) : 0), 0)
            })
        },
        widths() {
            const spans = this.spans()
            const openStages = spans.filter((span) => span === null).length
            const total = spans.reduce((sum, span) => sum + (span ?? 0), 0) || 1

            return spans.map((span) => span === null ? this.openWidth : (100 - this.openWidth * openStages) * span / total)
        },
        offset(index) {
            return this.widths().slice(0, index).reduce((sum, width) => sum + width, 0)
        },
    }"
    class="fi-settings-stages-bar"
>
    <div class="fi-settings-stages-bar-ends">
        <span>{{ $startLabel }}</span>
        <span>{{ $endLabel }}</span>
    </div>

    <div class="fi-settings-stages-bar-track">
        <template x-for="(stage, index) in stages" :key="index">
            <div
                :style="`width: ${widths()[index]}%; --stage-color: ${stage.color}`"
                :class="{ 'fi-settings-stages-bar-open': spans()[index] === null }"
                class="fi-settings-stages-bar-segment"
            ></div>
        </template>
    </div>

    <div class="fi-settings-stages-bar-ticks">
        <template x-for="(stage, index) in stages" :key="index">
            <span x-show="stage.field" :style="`left: ${offset(index)}%`" x-text="stage.field ? value(stage.field) : ''"></span>
        </template>
    </div>

    <div class="fi-settings-stages-bar-legend">
        <template x-for="(stage, index) in stages" :key="index">
            <span :style="`--stage-color: ${stage.color}`">
                <span class="fi-settings-stage-dot"></span><span x-text="stage.name"></span>
            </span>
        </template>
    </div>
</div>
