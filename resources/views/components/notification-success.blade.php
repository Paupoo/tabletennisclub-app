 <div class="pt-12">
     <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
         <div
             {{ $attributes->merge(['class' => 'overflow-hidden bg-green-400 shadow-xs dark:bg-gray-800 sm:rounded-lg inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest transition ease-in-out duration-150']) }}>
             {{ $slot }}
         </div>
     </div>
 </div>
