    <x-app-layout>
        <div x-data="tournament()" x-init="init" class="relative w-max-7xl container rounded-xl p-10 bg-white m-auto mt-20">
            <svg class="absolute top-0 left-0 w-full h-full pointer-events-none z-0" x-ref="svg"></svg>

            <form action="" class="bg-neutral-300 my-8 z-10 relative">
                <label for="totalRounds">
                    Total Rounds
                    <input type="number" x-model.number="totalRound" min="1" class="border p-1" @input="refreshLines" />
                </label>
            </form>

            <div class="flex flex-wrap flex-row-reverse justify-center gap-6 mt-6 z-10 relative">
                <template x-for="round in totalRound" :key="round">
                    <div :data-round="round" class=" rounded-md p-4 w-60 flex-col gap-4 justify-evenly">
                        <h2 x-text="'Round ' + round" class="mb-4 text-center font-semibold"></h2>

                        <ul class="flex flex-col gap-2 items-center justify-around h-dvh">
                            <template x-for="match in Math.pow(2, round)/2" :key="match">
                                <li :data-match="match" class="flex flex-row relative h-12 w-48 rounded-xl text-center justify-center items-center shadow">
                                    <div x-text="match" class="flex w-1/6 h-full text-md items-center justify-center font-semibold bg-indigo-500 rounded-l-lg text"></div>
                                    <div class="w-5/6">
                                        <span class="flex rounded-tr-lg h-1/2 w-full bg-indigo-200 items-center justify-center  ">{{ fake()->firstName . ' ' . fake()->lastName()}}</span>
                                        <span class="flex rounded-br-lg h-1/2 w-full bg-yellow-200 items-center justify-center">{{ fake()->firstName . ' ' . fake()->lastName()}}</span>
                                    </div>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>
            </div>
        </div>

        <script>
            function tournament() {
                return {
                    totalRound: 3,
                    svg: null,

                    init() {
                        this.svg = this.$refs.svg
                        this.refreshLines()
                        window.addEventListener('resize', this.refreshLines)
                    },

                    refreshLines() {
                        this.$nextTick(() => this.drawLines())
                    },

                    drawLines() {
                        if (!this.svg) return
                        this.svg.innerHTML = ''
                        const allRounds = [...document.querySelectorAll('[data-round]')]
                            .sort((a, b) => Number(b.dataset.round) - Number(a.dataset.round))

                        for (let i = 0; i < allRounds.length - 1; i++) {
                            const current = allRounds[i].querySelectorAll('[data-match]')
                            const next = allRounds[i + 1].querySelectorAll('[data-match]')

                            current.forEach((matchEl, idx) => {
                                const source = matchEl.getBoundingClientRect()
                                const target = next[Math.floor(idx / 2)]?.getBoundingClientRect()
                                if (!target) return

                                const svgRect = this.svg.getBoundingClientRect()

                                const x1 = source.left + source.width
                                const y1 = source.top + source.height / 2
                                const x2 = target.left
                                const y2 = target.top + target.height / 2

                                const startX = x1 - svgRect.left
                                const startY = y1 - svgRect.top
                                const endX = x2 - svgRect.left
                                const endY = y2 - svgRect.top

                                const controlX = (startX + endX) / 2

                                const path = document.createElementNS("http://www.w3.org/2000/svg", "path")
                                path.setAttribute('d', `M ${startX},${startY} C ${controlX},${startY} ${controlX},${endY} ${endX},${endY}`)
                                path.setAttribute('stroke', 'gray')
                                path.setAttribute('stroke-width', '2')
                                path.setAttribute('fill', 'none')

                                // Animation progressive
                                const length = path.getTotalLength()
                                path.setAttribute('stroke-dasharray', length)
                                path.setAttribute('stroke-dashoffset', length)
                                path.style.transition = 'stroke-dashoffset 1s ease-out'
                                requestAnimationFrame(() => {
                                    path.setAttribute('stroke-dashoffset', '0')
                                })

                                this.svg.appendChild(path)

                            })
                        }
                    }
                }
            }
        </script>
    </x-app-layout>
