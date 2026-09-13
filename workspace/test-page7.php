<!DOCTYPE html>
<html lang="en" class="dark scroll-smooth">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KAMIGAMI & ÆSIR CELESTIAL RAILWAYS | 神々の天界軌道・BIFRÖST-TAKAMA LINE</title>
  <meta name="description" content="The inter-pantheon hyper-railway transit authority linking Yggdrasil's Nine Realms with Takamagahara and the Celestial Heavens.">

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@600;700;900&family=Cinzel:wght@400;600;700;800&family=Noto+Serif+JP:wght@300;400;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            void: '#07080D',
            astral: '#0B0D18',
            vermilion: {
              DEFAULT: '#D93829',
              glow: '#FF4D36',
              deep: '#931812',
            },
            bifrost: {
              cyan: '#00F0FF',
              purple: '#A855F7',
              pink: '#EC4899',
              gold: '#F59E0B',
            },
            divine: {
              gold: '#E5B869',
              shimmer: '#FCE7A1',
              dark: '#947029',
            },
            runic: '#38BDF8',
            sakura: '#F472B6',
            shinto: '#181E29',
          },
          fontFamily: {
            cinzel: ['"Cinzel"', 'serif'],
            cinzelDeco: ['"Cinzel Decorative"', 'serif'],
            jp: ['"Noto Serif JP"', 'serif'],
            sans: ['"Plus Jakarta Sans"', 'sans-serif'],
          },
          backgroundImage: {
            'torii-pattern': "radial-gradient(circle at 50% 50%, rgba(217, 56, 41, 0.08) 0%, transparent 60%)",
            'bifrost-gradient': "linear-gradient(135deg, rgba(0,240,255,0.15) 0%, rgba(168,85,247,0.15) 50%, rgba(236,72,153,0.15) 100%)",
          },
          animation: {
            'pulse-glow': 'pulseGlow 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
            'aurora': 'auroraShift 16s ease infinite alternate',
            'float': 'floatSlow 6s ease-in-out infinite',
            'spin-slow': 'spin 30s linear infinite',
          },
          keyframes: {
            pulseGlow: {
              '0%, 100%': {
                opacity: '0.4',
                filter: 'drop-shadow(0 0 15px rgba(229,184,105,0.4))'
              },
              '50%': {
                opacity: '0.9',
                filter: 'drop-shadow(0 0 25px rgba(0,240,255,0.8))'
              },
            },
            auroraShift: {
              '0%': {
                backgroundPosition: '0% 50%'
              },
              '100%': {
                backgroundPosition: '100% 50%'
              },
            },
            floatSlow: {
              '0%, 100%': {
                transform: 'translateY(0px)'
              },
              '50%': {
                transform: 'translateY(-8px)'
              },
            }
          }
        }
      }
    }
  </script>

  <style>
    /* Custom Scrollbar */
    ::-webkit-scrollbar {
      width: 7px;
      height: 7px;
    }

    ::-webkit-scrollbar-track {
      background: #07080D;
    }

    ::-webkit-scrollbar-thumb {
      background: #252A3D;
      border-radius: 4px;
      border: 1px solid rgba(229, 184, 105, 0.2);
    }

    ::-webkit-scrollbar-thumb:hover {
      background: #D93829;
    }

    /* Traditional Seigaiha / Asanoha subtle overlay */
    .bg-seigaiha {
      background-image: radial-gradient(circle at 100% 150%, #151926 24%, #0E121E 25%, #0E121E 28%, #151926 29%, #151926 36%, #0E121E 37%, #0E121E 40%, transparent 40%, transparent),
        radial-gradient(circle at 0 150%, #151926 24%, #0E121E 25%, #0E121E 28%, #151926 29%, #151926 36%, #0E121E 37%, #0E121E 40%, transparent 40%, transparent),
        radial-gradient(circle at 50% 100%, #151926 10%, #0E121E 11%, #0E121E 23%, #151926 24%, #151926 30%, #0E121E 31%, #0E121E 43%, transparent 44%, transparent),
        radial-gradient(circle at 100% 50%, #151926 5%, #0E121E 6%, #0E121E 15%, #151926 16%, #151926 20%, #0E121E 21%, #0E121E 30%, transparent 31%, transparent),
        radial-gradient(circle at 0 50%, #151926 5%, #0E121E 6%, #0E121E 15%, #151926 16%, #151926 20%, #0E121E 21%, #0E121E 30%, transparent 31%, transparent);
      background-size: 80px 40px;
    }

    /* Glowing borders and runic gold trims */
    .divine-border {
      border: 1px solid rgba(229, 184, 105, 0.25);
      box-shadow: inset 0 0 12px rgba(229, 184, 105, 0.05), 0 0 20px rgba(0, 0, 0, 0.7);
    }

    .divine-border-vermilion {
      border: 1px solid rgba(217, 56, 41, 0.4);
      box-shadow: 0 0 15px rgba(217, 56, 41, 0.15);
    }

    .bifrost-text-gradient {
      background: linear-gradient(120deg, #FFFFFF 15%, #FDE68A 35%, #6EE7B7 55%, #60A5FA 75%, #F472B6 95%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .gold-foil-text {
      background: linear-gradient(135deg, #FFF0B8 0%, #D4AF37 45%, #AA7C11 75%, #F8E5A1 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    /* Torii Gate stylized SVG backdrop */
    .torii-silhouette {
      mask-image: linear-gradient(to bottom, rgba(0, 0, 0, 1) 50%, rgba(0, 0, 0, 0) 100%);
    }

    /* Ticket Perforation */
    .ticket-rip {
      background-image: radial-gradient(circle at center, transparent 8px, #0F121E 9px);
      background-size: 24px 24px;
      background-position: -12px;
    }

    /* Print styles for Celestial Pass */
    @media print {
      body * {
        visibility: hidden;
      }

      #print-ticket-area,
      #print-ticket-area * {
        visibility: visible;
      }

      #print-ticket-area {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
      }
    }
  </style>
</head>

<body class="bg-void text-slate-100 font-sans selection:bg-vermilion selection:text-white antialiased overflow-x-hidden min-h-screen relative">

  <!-- Interactive Canvas: Bifröst Prismatic Rails, Sakura & Runic Particles -->
  <canvas id="celestial-canvas" class="fixed inset-0 pointer-events-none z-0 opacity-60"></canvas>

  <!-- Ambient Light Orbs -->
  <div class="fixed top-[-10%] left-[20%] w-[500px] h-[500px] rounded-full bg-vermilion/10 blur-[130px] pointer-events-none z-0"></div>
  <div class="fixed top-[40%] right-[-10%] w-[600px] h-[600px] rounded-full bg-runic/10 blur-[150px] pointer-events-none z-0"></div>
  <div class="fixed bottom-[-10%] left-[10%] w-[500px] h-[500px] rounded-full bg-bifrost-purple/10 blur-[140px] pointer-events-none z-0"></div>

  <!-- Top Announcement Bar: Live Cosmic Alignment Status -->
  <aside aria-label="Pantheon Transit Status" class="relative z-50 bg-astral/90 border-b border-divine-gold/20 text-xs py-1.5 px-4 backdrop-blur-md">
    <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-2">
      <div class="flex items-center space-x-3">
        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold tracking-widest bg-vermilion text-white uppercase animate-pulse">
          LIVE STATUS
        </span>
        <span class="text-slate-300 flex items-center gap-1.5 font-medium">
          <span class="text-divine-gold">⚡ Yggdrasil-Bifröst Link:</span> Optimal (99.8% Aether Density)
          <span class="text-slate-500">|</span>
          <span class="text-sakura">🌸 Takamagahara Gates:</span> Clear Skies, Inari Shrine Lights Active
        </span>
      </div>
      <div class="flex items-center space-x-4 text-slate-400">
        <div class="flex items-center space-x-2">
          <span class="text-amber-400 text-xs">☀️</span>
          <span id="celestial-clock" class="font-mono text-xs text-divine-gold">ERA REIWA-RAGNARÖK • 2026.04.14 • 19:42:08 TC</span>
        </div>
        <!-- Soundscape Button -->
        <button id="soundscape-toggle" class="flex items-center gap-1 px-2.5 py-0.5 rounded border border-divine-gold/30 hover:border-divine-gold text-slate-300 hover:text-white transition-all text-[11px] bg-white/5 active:scale-95" title="Toggle Sanctuary Ambience">
          <span id="sound-icon">🔔</span>
          <span id="sound-status">Bell Chime: Off</span>
        </button>
      </div>
    </div>
  </aside>

  <!-- Main Navigation Header -->
  <header class="sticky top-0 z-40 bg-void/85 backdrop-blur-xl border-b border-divine-gold/20 transition-all duration-300" id="main-nav">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">

      <!-- Brand Logo: The Dual Seal of Bifröst & Torii -->
      <a href="#" class="flex items-center gap-3.5 group">
        <div class="relative w-12 h-12 flex items-center justify-center rounded-xl bg-gradient-to-br from-shinto via-astral to-void border border-divine-gold/40 shadow-lg group-hover:border-vermilion transition-all duration-500">
          <!-- Torii + Rune Vegvísir Hybrid Emblem -->
          <svg class="w-7 h-7 text-divine-gold group-hover:text-vermilion transition-colors duration-300" viewBox="0 0 48 48" fill="none" stroke="currentColor">
            <!-- Torii Roof & Crossbeams -->
            <path d="M6 14C14 11 34 11 42 14" stroke-width="3" stroke-linecap="round" />
            <path d="M10 18H38" stroke-width="2.5" stroke-linecap="round" />
            <!-- Torii Pillars -->
            <path d="M16 18V42M32 18V42" stroke-width="3" stroke-linecap="round" />
            <!-- Center Norse Runic Spark / Bifröst Ray -->
            <circle cx="24" cy="27" r="4" stroke="#00F0FF" stroke-width="2" />
            <path d="M24 21V33M18 27H30" stroke="#00F0FF" stroke-width="1.8" />
            <path d="M20 23L28 31M28 23L20 31" stroke="#F59E0B" stroke-width="1.2" />
          </svg>
          <div class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-vermilion rounded-full ring-2 ring-void"></div>
        </div>
        <div>
          <div class="flex items-center gap-2">
            <span class="font-cinzelDeco font-bold text-lg md:text-xl tracking-wider text-white flex items-center gap-1.5">
              KAMIGAMI <span class="text-divine-gold">&</span> ÆSIR
            </span>
            <span class="hidden sm:inline-block px-1.5 py-0.5 text-[9px] uppercase tracking-widest bg-vermilion/20 border border-vermilion/50 text-vermilion rounded font-semibold">
              TRANSIT
            </span>
          </div>
          <p class="font-jp text-[11px] text-slate-400 tracking-widest flex items-center gap-1">
            神々の天界軌道 <span class="text-slate-600">|</span> BIFRÖST EXPRESSWAY
          </p>
        </div>
      </a>

      <!-- Desktop Nav Links -->
      <nav class="hidden lg:flex items-center space-x-8 text-sm font-medium tracking-wide">
        <a href="#planner" class="text-slate-300 hover:text-divine-gold transition-colors flex items-center gap-1.5 py-2 relative group">
          <span>Book Celestial Pass</span>
          <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-divine-gold transition-all duration-300 group-hover:w-full"></span>
        </a>
        <a href="#transit-map" class="text-slate-300 hover:text-divine-gold transition-colors flex items-center gap-1.5 py-2 relative group">
          <span>Live Realm Map</span>
          <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-divine-gold transition-all duration-300 group-hover:w-full"></span>
        </a>
        <a href="#departures" class="text-slate-300 hover:text-divine-gold transition-colors flex items-center gap-1.5 py-2 relative group">
          <span>Departures Board</span>
          <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-divine-gold transition-all duration-300 group-hover:w-full"></span>
        </a>
        <a href="#fleet" class="text-slate-300 hover:text-divine-gold transition-colors flex items-center gap-1.5 py-2 relative group">
          <span>Sacred Fleet</span>
          <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-divine-gold transition-all duration-300 group-hover:w-full"></span>
        </a>
        <a href="#oracle" class="text-slate-300 hover:text-divine-gold transition-colors flex items-center gap-1.5 py-2 relative group">
          <span>Deity Oracle</span>
          <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-divine-gold transition-all duration-300 group-hover:w-full"></span>
        </a>
      </nav>

      <!-- Quick Action CTA -->
      <div class="hidden sm:flex items-center space-x-3">
        <button onclick="document.getElementById('planner').scrollIntoView({behavior: 'smooth'})" class="px-5 py-2.5 rounded-lg bg-gradient-to-r from-vermilion to-vermilion-glow hover:to-amber-500 text-white font-semibold text-xs tracking-wider uppercase transition-all duration-300 shadow-lg shadow-vermilion/30 hover:shadow-vermilion/50 flex items-center gap-2 group transform active:scale-95 border border-amber-300/30">
          <span>Dispatch Train</span>
          <span class="group-hover:translate-x-1 transition-transform">→</span>
        </button>
      </div>

      <!-- Mobile Menu Toggle Button -->
      <button id="mobile-menu-btn" class="lg:hidden p-2.5 rounded-lg text-slate-400 hover:text-white bg-slate-800/40 border border-slate-700/60 focus:outline-none" aria-label="Toggle Navigation">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path id="menu-icon-open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
          <path id="menu-icon-close" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
      </button>
    </div>

    <!-- Mobile Navigation Drawer -->
    <div id="mobile-menu" class="hidden lg:hidden bg-astral/95 border-b border-divine-gold/20 px-6 py-5 space-y-4 backdrop-blur-2xl">
      <a href="#planner" class="mobile-nav-link block text-base font-medium text-slate-200 hover:text-divine-gold">Book Celestial Pass</a>
      <a href="#transit-map" class="mobile-nav-link block text-base font-medium text-slate-200 hover:text-divine-gold">Live Realm Map</a>
      <a href="#departures" class="mobile-nav-link block text-base font-medium text-slate-200 hover:text-divine-gold">Departures Board</a>
      <a href="#fleet" class="mobile-nav-link block text-base font-medium text-slate-200 hover:text-divine-gold">Sacred Fleet</a>
      <a href="#oracle" class="mobile-nav-link block text-base font-medium text-slate-200 hover:text-divine-gold">Mimir & Omoikane Oracle</a>
      <div class="pt-2">
        <button onclick="document.getElementById('planner').scrollIntoView({behavior: 'smooth'}); toggleMobileMenu();" class="w-full py-3 text-center rounded-lg bg-vermilion text-white font-semibold text-xs tracking-wider uppercase">
          Quick Book Ticket
        </button>
      </div>
    </div>
  </header>

  <!-- HERO SECTION: The Confluence of Two Heavens -->
  <section class="relative z-10 pt-12 pb-24 md:pt-20 md:pb-32 px-4 sm:px-6 lg:px-8 overflow-hidden">
    <!-- Atmospheric Background Rings / Runes & Torii Arch Backdrop -->
    <div class="max-w-7xl mx-auto">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">

        <!-- Left Hero Text & Storytelling -->
        <div class="lg:col-span-7 space-y-6 text-left">

          <div class="inline-flex items-center gap-2.5 px-3.5 py-1.5 rounded-full bg-slate-900/80 border border-divine-gold/40 text-xs text-divine-gold">
            <span class="w-2 h-2 rounded-full bg-vermilion animate-ping"></span>
            <span class="font-cinzel font-semibold tracking-wider uppercase">The Trans-Pantheon Concordat</span>
            <span class="text-slate-500">|</span>
            <span class="font-jp text-[11px] text-sakura">天の浮橋・ビフレスト直通</span>
          </div>

          <h1 class="text-4xl sm:text-5xl xl:text-6xl font-cinzelDeco font-extrabold tracking-tight leading-[1.15]">
            RIDE THE <span class="bifrost-text-gradient">BIFRÖST</span> TO <span class="gold-foil-text">TAKAMAGAHARA</span>
          </h1>

          <p class="text-slate-300 text-base sm:text-lg max-w-2xl font-normal leading-relaxed">
            The hyper-dimensional railway spanning Odin’s Nine Roots and Amaterasu’s Sunken Sun Isles. Travel from <strong class="text-white">Valhalla Central</strong> to the <strong class="text-vermilion-glow">Fushimi Fox Shrine Nexus</strong> in under four celestial beats at Mach 4.2.
          </p>

          <!-- Key Metrics / High Velocity Stats -->
          <div class="grid grid-cols-3 gap-3 pt-2 max-w-lg">
            <div class="p-3.5 rounded-xl bg-slate-900/60 border border-divine-gold/20 backdrop-blur-sm">
              <div class="text-xs text-slate-400 font-jp">最高速度 / Max Speed</div>
              <div class="text-xl sm:text-2xl font-bold font-mono text-divine-gold tracking-tight">4,820 <span class="text-xs font-normal">km/s</span></div>
              <div class="text-[10px] text-slate-500">Lightning-Warp</div>
            </div>
            <div class="p-3.5 rounded-xl bg-slate-900/60 border border-divine-gold/20 backdrop-blur-sm">
              <div class="text-xs text-slate-400 font-jp">交差界境 / Realms</div>
              <div class="text-xl sm:text-2xl font-bold font-mono text-vermilion-glow tracking-tight">16 <span class="text-xs font-normal">Hubs</span></div>
              <div class="text-[10px] text-slate-500">Norse & Shinto</div>
            </div>
            <div class="p-3.5 rounded-xl bg-slate-900/60 border border-divine-gold/20 backdrop-blur-sm">
              <div class="text-xs text-slate-400 font-jp">定時運行率 / On-Time</div>
              <div class="text-xl sm:text-2xl font-bold font-mono text-cyan-400 tracking-tight">99.98<span class="text-xs font-normal">%</span></div>
              <div class="text-[10px] text-slate-500">Heimdall Supervised</div>
            </div>
          </div>

          <!-- CTA Buttons -->
          <div class="flex flex-wrap items-center gap-4 pt-4">
            <button onclick="document.getElementById('planner').scrollIntoView({behavior: 'smooth'})" class="px-7 py-3.5 rounded-xl bg-gradient-to-r from-vermilion via-red-600 to-amber-600 hover:brightness-110 text-white font-cinzel font-bold text-sm tracking-wider uppercase transition-all duration-300 shadow-xl shadow-vermilion/30 flex items-center gap-3 active:scale-95 border border-white/20">
              <svg class="w-5 h-5 text-amber-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path>
              </svg>
              <span>Reserve Celestial Seat</span>
            </button>

            <button onclick="document.getElementById('transit-map').scrollIntoView({behavior: 'smooth'})" class="px-6 py-3.5 rounded-xl bg-slate-900/80 hover:bg-slate-800 text-slate-200 font-cinzel font-semibold text-sm tracking-wider uppercase transition-all duration-300 border border-divine-gold/30 hover:border-divine-gold flex items-center gap-2">
              <svg class="w-5 h-5 text-divine-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
              </svg>
              <span>Explore Realm Map</span>
            </button>
          </div>

          <!-- Quick Deity Conductor Avatars preview -->
          <div class="pt-4 flex items-center gap-3">
            <span class="text-xs text-slate-400 font-medium">Consecrated by:</span>
            <div class="flex -space-x-2">
              <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-vermilion-deep border-2 border-void text-xs font-bold font-jp text-amber-200" title="Amaterasu (Sun Goddess)">照</span>
              <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-950 border-2 border-void text-xs font-bold font-cinzel text-cyan-300" title="Odin Allfather">ᚩ</span>
              <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-amber-900 border-2 border-void text-xs font-bold font-cinzel text-amber-400" title="Thor (Thunder)">ᚦ</span>
              <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-emerald-950 border-2 border-void text-xs font-bold font-jp text-emerald-300" title="Susanoo (Storm God)">須</span>
              <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-pink-950 border-2 border-void text-xs font-bold font-jp text-pink-300" title="Inari (Fertility & Fox)">稲</span>
            </div>
            <span class="text-xs text-slate-400 font-jp">八百万神 × エーシル神族</span>
          </div>

        </div>

        <!-- Right Hero Visual: Holographic Shinkansen-Valkyrie Train Cockpit Graphic -->
        <div class="lg:col-span-5 relative">
          <div class="relative mx-auto max-w-md lg:max-w-none">

            <!-- Radiant Aura Frame -->
            <div class="absolute -inset-1 bg-gradient-to-r from-vermilion via-bifrost-purple to-runic rounded-3xl blur-xl opacity-40 animate-pulse"></div>

            <div class="relative rounded-2xl bg-astral/95 border border-divine-gold/40 p-6 shadow-2xl backdrop-blur-xl overflow-hidden">

              <!-- Shinto Shimenawa & Rune Top Ribbon -->
              <div class="flex items-center justify-between border-b border-divine-gold/20 pb-4 mb-4">
                <div class="flex items-center gap-2">
                  <span class="w-3 h-3 rounded-full bg-red-500"></span>
                  <span class="font-mono text-xs text-slate-300 uppercase tracking-widest">TRANSCENDENT TRAIN ID</span>
                </div>
                <div class="font-mono text-xs text-divine-gold font-bold">#AMTR-ODIN-990</div>
              </div>

              <!-- Animated Train Visual / Radar View -->
              <div class="relative h-56 rounded-xl bg-gradient-to-b from-void to-slate-900/90 border border-slate-800 flex flex-col justify-center items-center overflow-hidden group">
                <!-- Grid Overlay -->
                <div class="absolute inset-0 bg-seigaiha opacity-30"></div>

                <!-- Track Line SVG -->
                <svg class="absolute inset-x-0 bottom-6 w-full h-16 pointer-events-none" preserveAspectRatio="none" viewBox="0 0 400 60">
                  <path d="M0,45 Q200,20 400,45" stroke="#38BDF8" stroke-width="2" fill="none" stroke-dasharray="6 4" class="opacity-60" />
                  <path d="M0,50 Q200,25 400,50" stroke="#D93829" stroke-width="3" fill="none" />
                  <!-- Moving train dot on track -->
                  <circle cx="210" cy="37" r="5" fill="#FCE7A1">
                    <animate attributeName="cx" values="0;400" dur="4s" repeatCount="indefinite" />
                  </circle>
                  <circle cx="210" cy="37" r="12" fill="none" stroke="#FCE7A1" stroke-width="1.5" opacity="0.6">
                    <animate attributeName="cx" values="0;400" dur="4s" repeatCount="indefinite" />
                  </circle>
                </svg>

                <!-- Center Train Icon Emblem: Aerodynamic Dragon-Shinkansen with Rune Crest -->
                <div class="relative z-10 flex flex-col items-center">
                  <div class="w-24 h-24 rounded-2xl bg-gradient-to-br from-vermilion-deep to-astral border border-divine-gold/60 flex items-center justify-center shadow-inner relative group-hover:scale-105 transition-transform duration-500">
                    <span class="text-4xl filter drop-shadow-[0_0_12px_rgba(229,184,105,0.8)]">🚅</span>
                    <span class="absolute -top-2 -right-2 px-1.5 py-0.5 rounded text-[9px] font-mono font-bold bg-divine-gold text-slate-950 uppercase">SLEIPNIR-X</span>
                  </div>
                  <h2 class="mt-3 font-cinzel font-bold text-sm tracking-widest text-slate-100 uppercase">
                    Solar Dragon Shinkansen
                  </h2>
                  <span class="text-[11px] font-mono text-cyan-400">Departing Himinbjörg Bridge ➔ Izumo Zenith</span>
                </div>

                <!-- Runes Floating in corner -->
                <div class="absolute top-3 left-3 text-xs font-cinzel text-divine-gold/60 font-mono">ᚨ · ᚱ · ᛏ · ᛋ</div>
                <div class="absolute top-3 right-3 text-xs font-jp text-vermilion/80">天照大御神・主宰</div>
              </div>

              <!-- Quick Info Grid -->
              <div class="mt-4 grid grid-cols-2 gap-3 text-xs">
                <div class="p-2.5 rounded-lg bg-void/60 border border-slate-800">
                  <div class="text-slate-400 text-[10px] uppercase font-mono">Current Position</div>
                  <div class="font-semibold text-slate-200 mt-0.5">Bifröst 4th Archway</div>
                </div>
                <div class="p-2.5 rounded-lg bg-void/60 border border-slate-800">
                  <div class="text-slate-400 text-[10px] uppercase font-mono">Next Stop</div>
                  <div class="font-semibold text-divine-gold mt-0.5">Takamagahara Grand Gate</div>
                </div>
              </div>

              <!-- Live Beacon Bar -->
              <div class="mt-4 flex items-center justify-between px-3 py-2 rounded-lg bg-vermilion/10 border border-vermilion/30 text-xs">
                <div class="flex items-center gap-2">
                  <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                  <span class="text-slate-300 font-medium">Bifröst Prism Shield: 100%</span>
                </div>
                <span class="text-emerald-400 font-mono text-[11px]">ALL GATES OPEN</span>
              </div>

            </div>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- INTERACTIVE BOOKING ENGINE & CELESTIAL PASS BUILDER -->
  <section id="planner" class="relative z-20 py-20 bg-astral/60 border-y border-divine-gold/20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

      <div class="text-center max-w-3xl mx-auto mb-14">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-vermilion/10 border border-vermilion/30 text-vermilion text-xs font-semibold tracking-wider uppercase mb-3">
          <span>神聖なる切符発行所</span> • CELESTIAL PASS DISPATCH
        </div>
        <h2 class="text-3xl sm:text-4xl font-cinzelDeco font-bold tracking-wide text-white">
          BOOK YOUR TRANS-REALM VOYAGE
        </h2>
        <p class="mt-3 text-slate-400 text-sm sm:text-base font-normal">
          Select your departure sphere, celestial destination, patron deity conductor, and travel class. Generate an official mythic talisman pass with instant boarding QR verification.
        </p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

        <!-- Booking Form / Selector Panel -->
        <div class="lg:col-span-7 bg-void/90 rounded-2xl border border-divine-gold/30 p-6 sm:p-8 shadow-2xl relative">
          <div class="space-y-6">

            <!-- Origin & Destination Selectors with Swap Button -->
            <div class="relative grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">

              <!-- Origin Station -->
              <div>
                <label for="origin-select" class="block text-xs font-mono tracking-wider text-slate-300 uppercase mb-2 flex items-center justify-between">
                  <span>Origin Station (発駅)</span>
                  <span class="text-runic text-[10px]">NORSE / SHINTO</span>
                </label>
                <div class="relative">
                  <select id="origin-select" class="w-full bg-astral border border-slate-700 hover:border-divine-gold rounded-xl px-4 py-3.5 text-sm font-medium text-white focus:outline-none focus:ring-2 focus:ring-divine-gold/50 appearance-none cursor-pointer">
                    <optgroup label="Norse Realms (Yggdrasil Branches)">
                      <option value="valhalla" selected>Valhalla Central (Asgard)</option>
                      <option value="himinbjorg">Himinbjörg Bifröst Terminal</option>
                      <option value="jotunheim">Jötunheim Frost Spire</option>
                      <option value="niflheim">Niflheim Mist Platform</option>
                      <option value="vanaheim">Vanaheim Blooming Groves</option>
                      <option value="nidavellir">Niðavellir Deep Forge Station</option>
                      <option value="helheim">Helheim Underworld Depths</option>
                    </optgroup>
                    <optgroup label="Japanese Kami Spheres (Takamagahara & Below)">
                      <option value="takamagahara">Takamagahara High Spire (Amaterasu)</option>
                      <option value="izumo">Izumo Grand Junction (Ōkuninushi)</option>
                      <option value="fushimi">Fushimi Inari Vermilion Loop</option>
                      <option value="tsukuyomi">Tsukuyomi Lunar Terminal</option>
                      <option value="ryugu">Ryūgū-jō Abyssal Water Station</option>
                      <option value="yomi">Yomi-no-Kuni Gate of the Departed</option>
                      <option value="fuji">Mount Fuji Tengu Crest</option>
                    </optgroup>
                  </select>
                  <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-400">
                    ▼
                  </div>
                </div>
              </div>

              <!-- Swap Button (Center) -->
              <button id="swap-stations-btn" type="button" class="sm:absolute sm:left-1/2 sm:top-[38px] sm:-translate-x-1/2 z-10 w-10 h-10 mx-auto rounded-full bg-slate-800 border border-divine-gold/40 text-divine-gold hover:text-white hover:bg-vermilion transition-all flex items-center justify-center shadow-lg active:rotate-180" title="Swap Origin and Destination">
                ⇄
              </button>

              <!-- Destination Station -->
              <div>
                <label for="dest-select" class="block text-xs font-mono tracking-wider text-slate-300 uppercase mb-2 flex items-center justify-between">
                  <span>Destination Station (着駅)</span>
                  <span class="text-sakura text-[10px]">CELESTIAL TERMINUS</span>
                </label>
                <div class="relative">
                  <select id="dest-select" class="w-full bg-astral border border-slate-700 hover:border-divine-gold rounded-xl px-4 py-3.5 text-sm font-medium text-white focus:outline-none focus:ring-2 focus:ring-divine-gold/50 appearance-none cursor-pointer">
                    <optgroup label="Japanese Kami Spheres (Takamagahara & Below)">
                      <option value="takamagahara" selected>Takamagahara High Spire (Amaterasu)</option>
                      <option value="izumo">Izumo Grand Junction (Ōkuninushi)</option>
                      <option value="fushimi">Fushimi Inari Vermilion Loop</option>
                      <option value="tsukuyomi">Tsukuyomi Lunar Terminal</option>
                      <option value="ryugu">Ryūgū-jō Abyssal Water Station</option>
                      <option value="yomi">Yomi-no-Kuni Gate of the Departed</option>
                      <option value="fuji">Mount Fuji Tengu Crest</option>
                    </optgroup>
                    <optgroup label="Norse Realms (Yggdrasil Branches)">
                      <option value="valhalla">Valhalla Central (Asgard)</option>
                      <option value="himinbjorg">Himinbjörg Bifröst Terminal</option>
                      <option value="jotunheim">Jötunheim Frost Spire</option>
                      <option value="niflheim">Niflheim Mist Platform</option>
                      <option value="vanaheim">Vanaheim Blooming Groves</option>
                      <option value="nidavellir">Niðavellir Deep Forge Station</option>
                      <option value="helheim">Helheim Underworld Depths</option>
                    </optgroup>
                  </select>
                  <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-400">
                    ▼
                  </div>
                </div>
              </div>

            </div>

            <!-- Patron Deity Conductor / Sacred Blessing Selection -->
            <div>
              <label class="block text-xs font-mono tracking-wider text-slate-300 uppercase mb-2">
                Conductor Deity & Divine Blessing (主宰神の加護)
              </label>
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5" id="deity-blessing-selector">

                <button type="button" data-deity="amaterasu" class="deity-btn active p-3 rounded-xl border border-divine-gold bg-vermilion/15 text-left transition-all hover:bg-white/5 group">
                  <div class="text-xl mb-1">☀️</div>
                  <div class="font-bold text-xs text-white group-hover:text-divine-gold">Amaterasu</div>
                  <div class="text-[10px] text-slate-400">Solar Daylight & Blessing</div>
                </button>

                <button type="button" data-deity="odin" class="deity-btn p-3 rounded-xl border border-slate-700 bg-void text-left transition-all hover:border-slate-500 hover:bg-white/5 group">
                  <div class="text-xl mb-1">🦅</div>
                  <div class="font-bold text-xs text-white group-hover:text-cyan-400">Odin Allfather</div>
                  <div class="text-[10px] text-slate-400">Raven Hugin Precognition</div>
                </button>

                <button type="button" data-deity="thor" class="deity-btn p-3 rounded-xl border border-slate-700 bg-void text-left transition-all hover:border-slate-500 hover:bg-white/5 group">
                  <div class="text-xl mb-1">⚡</div>
                  <div class="font-bold text-xs text-white group-hover:text-amber-400">Thor Mjölnir</div>
                  <div class="text-[10px] text-slate-400">High-Velocity Lightning Boost</div>
                </button>

                <button type="button" data-deity="inari" class="deity-btn p-3 rounded-xl border border-slate-700 bg-void text-left transition-all hover:border-slate-500 hover:bg-white/5 group">
                  <div class="text-xl mb-1">🦊</div>
                  <div class="font-bold text-xs text-white group-hover:text-pink-400">Inari Okami</div>
                  <div class="text-[10px] text-slate-400">Fox Attendants & Feast Service</div>
                </button>

              </div>
            </div>

            <!-- Date, Time & Class Selection -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

              <!-- Travel Date / Astral Conjunction -->
              <div>
                <label for="travel-date" class="block text-xs font-mono tracking-wider text-slate-300 uppercase mb-2">
                  Astral Date (運行日)
                </label>
                <input id="travel-date" type="date" value="2026-04-15" class="w-full bg-astral border border-slate-700 rounded-xl px-3.5 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-divine-gold/50">
              </div>

              <!-- Time of Departure -->
              <div>
                <label for="travel-time" class="block text-xs font-mono tracking-wider text-slate-300 uppercase mb-2">
                  Celestial Hour (発車時刻)
                </label>
                <select id="travel-time" class="w-full bg-astral border border-slate-700 rounded-xl px-3.5 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-divine-gold/50">
                  <option value="06:30">06:30 AM (Dawn Chorus)</option>
                  <option value="11:45" selected>11:45 AM (Zenith Sun)</option>
                  <option value="18:20">18:20 PM (Twilight Bifröst)</option>
                  <option value="23:15">23:15 PM (Tsukuyomi Midnight)</option>
                </select>
              </div>

              <!-- Passenger Class -->
              <div>
                <label for="travel-class" class="block text-xs font-mono tracking-wider text-slate-300 uppercase mb-2">
                  Carriage Tier (座席等級)
                </label>
                <select id="travel-class" class="w-full bg-astral border border-slate-700 rounded-xl px-3.5 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-divine-gold/50">
                  <option value="pilgrim" selected>Mortal Pilgrim Class</option>
                  <option value="valkyrie">Valkyrie / Samurai Envoy</option>
                  <option value="imperial">Imperial Deity First Suite</option>
                </select>
              </div>

            </div>

            <!-- Passenger Name & Runes Name -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label for="passenger-name" class="block text-xs font-mono tracking-wider text-slate-300 uppercase mb-2">
                  Traveler Name / Title (旅客名)
                </label>
                <input id="passenger-name" type="text" value="Lady Brynhild of Yamato" placeholder="Enter traveler name" class="w-full bg-astral border border-slate-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-divine-gold/50">
              </div>

              <div>
                <label for="sacred-talisman" class="block text-xs font-mono tracking-wider text-slate-300 uppercase mb-2">
                  Sacred Sigil / Talisman Protection (護符)
                </label>
                <select id="sacred-talisman" class="w-full bg-astral border border-slate-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-divine-gold/50">
                  <option value="omamori">Shinto Omamori (Safe Transit / 交通安全)</option>
                  <option value="vegvisir">Norse Vegvísir (Wayfinder Rune)</option>
                  <option value="aegishjalmur">Helm of Awe (Terror Ward)</option>
                  <option value="magatama">Imperial Magatama Jade Blessing</option>
                </select>
              </div>
            </div>

            <!-- Dynamic Price & Action Trigger -->
            <div class="pt-2 flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-slate-800">
              <div>
                <div class="text-xs text-slate-400 font-mono">Fare Calculation (神聖運賃):</div>
                <div class="flex items-baseline gap-2">
                  <span id="calculated-fare" class="text-2xl font-bold font-mono text-divine-gold">450</span>
                  <span class="text-xs text-slate-300 font-mono">Astral Auric Coins (神気硬貨)</span>
                </div>
              </div>

              <button id="generate-ticket-btn" type="button" class="w-full sm:w-auto px-8 py-3.5 rounded-xl bg-gradient-to-r from-vermilion via-red-600 to-amber-600 hover:brightness-110 text-white font-cinzel font-bold text-xs tracking-widest uppercase transition-all shadow-xl shadow-vermilion/30 active:scale-95 flex items-center justify-center gap-2">
                <span>Issue Sacred Boarding Pass</span>
                <span>⚡</span>
              </button>
            </div>

          </div>
        </div>

        <!-- Right Side: Live Interactive Boarding Pass Display -->
        <div class="lg:col-span-5">
          <div class="sticky top-24">

            <div class="text-xs font-mono text-slate-400 uppercase tracking-widest mb-3 flex items-center justify-between">
              <span>✦ Talisman Pass Preview</span>
              <button onclick="printTicket()" class="text-divine-gold hover:underline flex items-center gap-1 text-[11px]">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                </svg>
                <span>Print / Save Pass</span>
              </button>
            </div>

            <!-- The Divine Boarding Ticket Card -->
            <div id="print-ticket-area" class="relative rounded-2xl bg-gradient-to-b from-[#131625] via-[#0E111C] to-[#0A0D16] border-2 border-divine-gold/50 p-6 shadow-2xl overflow-hidden transition-all duration-300">

              <!-- Subtle Torii Seal Background Stamp -->
              <div class="absolute -right-8 -bottom-8 w-48 h-48 opacity-10 pointer-events-none">
                <svg viewBox="0 0 100 100" fill="currentColor" class="text-vermilion">
                  <path d="M10,25 Q50,15 90,25 L90,32 Q50,22 10,32 Z" />
                  <rect x="25" y="32" width="10" height="60" />
                  <rect x="65" y="32" width="10" height="60" />
                  <rect x="18" y="42" width="64" height="7" />
                </svg>
              </div>

              <!-- Ticket Top Header -->
              <div class="flex items-center justify-between border-b border-divine-gold/30 pb-3">
                <div class="flex items-center gap-2">
                  <span class="text-lg">⛩️</span>
                  <div>
                    <h3 class="font-cinzelDeco font-bold text-xs tracking-wider text-slate-100">KAMIGAMI & ÆSIR CELESTIAL PASS</h3>
                    <p class="text-[9px] font-jp text-divine-gold">天界高速鉄道連盟 • 神聖通行証</p>
                  </div>
                </div>
                <div class="text-right">
                  <span id="pass-class-badge" class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-amber-500/20 border border-amber-500/50 text-amber-300">
                    PILGRIM CLASS
                  </span>
                </div>
              </div>

              <!-- Journey Direction Display -->
              <div class="my-5 p-4 rounded-xl bg-void/80 border border-slate-800 flex items-center justify-between">
                <div>
                  <div class="text-[10px] font-mono text-slate-400">FROM</div>
                  <div id="pass-origin" class="font-cinzel font-bold text-base sm:text-lg text-white">Valhalla Central</div>
                  <div class="text-[10px] font-jp text-cyan-400">Asgard / アースガルズ</div>
                </div>

                <div class="flex flex-col items-center px-2">
                  <span class="text-[9px] font-mono text-divine-gold animate-pulse">BIFRÖST LINK</span>
                  <div class="w-16 h-0.5 bg-gradient-to-r from-cyan-400 via-amber-400 to-vermilion my-1"></div>
                  <span class="text-xs">🚅</span>
                </div>

                <div class="text-right">
                  <div class="text-[10px] font-mono text-slate-400">TO</div>
                  <div id="pass-dest" class="font-cinzel font-bold text-base sm:text-lg text-vermilion-glow">Takamagahara</div>
                  <div class="text-[10px] font-jp text-sakura">High Plain / 高天原</div>
                </div>
              </div>

              <!-- Passenger & Voyage Details Grid -->
              <div class="grid grid-cols-2 gap-3 text-xs mb-4">
                <div>
                  <span class="text-[10px] text-slate-400 uppercase font-mono">Traveler:</span>
                  <div id="pass-name" class="font-semibold text-slate-200 truncate">Lady Brynhild of Yamato</div>
                </div>
                <div>
                  <span class="text-[10px] text-slate-400 uppercase font-mono">Patron Deity:</span>
                  <div id="pass-deity" class="font-semibold text-amber-300">Amaterasu ☀️ (Solar Boon)</div>
                </div>
                <div>
                  <span class="text-[10px] text-slate-400 uppercase font-mono">Departure / Stardate:</span>
                  <div id="pass-date" class="font-mono text-slate-200">2026-04-15 • 11:45 AM</div>
                </div>
                <div>
                  <span class="text-[10px] text-slate-400 uppercase font-mono">Seat & Carriage:</span>
                  <div id="pass-seat" class="font-mono text-divine-gold font-bold">Car 03 • Seat 14-ᚱ (Rune)</div>
                </div>
              </div>

              <!-- Ticket Rip / Perforation Line -->
              <div class="relative py-2 flex items-center justify-between">
                <div class="w-4 h-4 rounded-full bg-astral -ml-8 border-r border-divine-gold/30"></div>
                <div class="w-full border-b border-dashed border-slate-700"></div>
                <div class="w-4 h-4 rounded-full bg-astral -mr-8 border-l border-divine-gold/30"></div>
              </div>

              <!-- Talisman Barcode & Authentic Verification Seal -->
              <div class="pt-3 flex items-center justify-between">
                <div>
                  <div class="text-[10px] font-mono text-slate-400">SACRED TALISMAN SEAL:</div>
                  <div id="pass-sigil" class="text-xs font-jp text-cyan-300 mt-0.5">Shinto Omamori (Safe Transit)</div>
                  <div class="text-[9px] font-mono text-slate-500 mt-1">VERIFIED BY HEIMDALL & SARUTAHIKO</div>
                </div>

                <!-- Dynamic SVG QR Code Stamp with Japanese Kanji Red Inkan Seal -->
                <div class="relative w-16 h-16 bg-white p-1 rounded-lg flex items-center justify-center shadow-md">
                  <!-- QR Code pattern simulated with SVG -->
                  <svg viewBox="0 0 25 25" class="w-full h-full text-slate-900 fill-current">
                    <path d="M1,1 h7 v7 h-7 z M3,3 v3 h3 v-3 z M17,1 h7 v7 h-7 z M19,3 v3 h3 v-3 z M1,17 h7 v7 h-7 z M3,19 v3 h3 v-3 z M10,1 h2 v4 h-2 z M14,1 h2 v2 h-2 z M10,6 h5 v2 h-5 z M1,10 h3 v2 h-3 z M6,10 h2 v5 h-2 z M10,10 h4 v4 h-4 z M16,10 h3 v2 h-3 z M21,10 h3 v5 h-3 z M1,14 h4 v2 h-4 z M10,16 h2 v3 h-2 z M13,16 h3 v2 h-3 z M18,17 h3 v2 h-3 z M10,21 h4 v3 h-4 z M16,21 h2 v3 h-2 z M20,21 h4 v3 h-4 z" />
                  </svg>
                  <!-- Red Imperial Seal Stamp overlay -->
                  <div class="absolute -bottom-2 -right-2 w-6 h-6 rounded-full bg-vermilion border border-white flex items-center justify-center text-[9px] font-bold text-white font-jp shadow">
                    神
                  </div>
                </div>
              </div>

            </div>

            <!-- Booking Confirmation Alert Toast -->
            <div id="booking-toast" class="hidden mt-3 p-3 rounded-xl bg-emerald-950/80 border border-emerald-500/50 text-emerald-300 text-xs flex items-center gap-2 animate-bounce">
              <span>✓</span>
              <span>Celestial reservation logged! Bifröst clearance granted.</span>
            </div>

          </div>
        </div>

      </div>

    </div>
  </section>

  <!-- INTERACTIVE LIVE CELESTIAL REALM TRANSIT MAP -->
  <section id="transit-map" class="relative z-20 py-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">

    <div class="text-center max-w-3xl mx-auto mb-10">
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-runic/10 border border-runic/30 text-cyan-300 text-xs font-semibold tracking-wider uppercase mb-3">
        <span>全界路線図</span> • THE SACRED INTER-REALM NETWORK
      </div>
      <h2 class="text-3xl sm:text-4xl font-cinzelDeco font-bold tracking-wide text-white">
        LIVE INTERACTIVE REALM MAP
      </h2>
      <p class="mt-3 text-slate-400 text-sm">
        Click any deity station on the network to view real-time meteorological conditions, guardian deities, arriving trains, and mythic lore.
      </p>
    </div>

    <!-- Map Filter Tabs -->
    <div class="flex flex-wrap items-center justify-center gap-2 mb-8" id="map-line-filters">
      <button class="filter-btn active px-4 py-2 rounded-xl text-xs font-cinzel font-semibold tracking-wider bg-slate-800 text-white border border-divine-gold/40 shadow-md" data-filter="all">
        All Inter-Pantheon Lines
      </button>
      <button class="filter-btn px-4 py-2 rounded-xl text-xs font-cinzel font-semibold tracking-wider bg-void text-slate-400 hover:text-white border border-slate-800" data-filter="bifrost">
        🌈 The Solar-Bifröst Radial
      </button>
      <button class="filter-btn px-4 py-2 rounded-xl text-xs font-cinzel font-semibold tracking-wider bg-void text-slate-400 hover:text-white border border-slate-800" data-filter="thunder">
        ⚡ Thor-Susanoo Storm Vanguard
      </button>
      <button class="filter-btn px-4 py-2 rounded-xl text-xs font-cinzel font-semibold tracking-wider bg-void text-slate-400 hover:text-white border border-slate-800" data-filter="underworld">
        🌑 Hel-Yomi Underworld Deep Line
      </button>
    </div>

    <!-- Map Container -->
    <div class="relative rounded-3xl bg-void/90 border border-divine-gold/30 p-4 sm:p-8 shadow-2xl overflow-hidden">

      <!-- Interactive SVG Realm Transit Schematic -->
      <div class="relative w-full aspect-[16/9] min-h-[480px] sm:min-h-[620px] rounded-2xl bg-[#090B14] border border-slate-800/80 overflow-hidden">

        <!-- Background Constellations and Sacred Grid -->
        <svg id="realm-svg-map" class="w-full h-full" viewBox="0 0 1000 600" xmlns="http://www.w3.org/2000/svg">
          <defs>
            <!-- Glowing Gradients -->
            <linearGradient id="bifrostGradient" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#00F0FF" />
              <stop offset="35%" stop-color="#F59E0B" />
              <stop offset="70%" stop-color="#EC4899" />
              <stop offset="100%" stop-color="#8B5CF6" />
            </linearGradient>

            <linearGradient id="stormGradient" x1="0%" y1="0%" x2="100%" y2="0%">
              <stop offset="0%" stop-color="#38BDF8" />
              <stop offset="50%" stop-color="#EAB308" />
              <stop offset="100%" stop-color="#EF4444" />
            </linearGradient>

            <linearGradient id="underworldGradient" x1="0%" y1="100%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#64748B" />
              <stop offset="50%" stop-color="#7C3AED" />
              <stop offset="100%" stop-color="#1E293B" />
            </linearGradient>

            <filter id="glow" x="-20%" y="-20%" width="140%" height="140%">
              <feGaussianBlur stdDeviation="4" result="blur" />
              <feComposite in="SourceGraphic" in2="blur" operator="over" />
            </filter>
          </defs>

          <!-- Cosmic Background Circles (Yggdrasil Rings & Shinto Mirror) -->
          <circle cx="500" cy="300" r="240" fill="none" stroke="rgba(229,184,105,0.06)" stroke-width="1.5" stroke-dasharray="6,6" />
          <circle cx="500" cy="300" r="160" fill="none" stroke="rgba(0,240,255,0.05)" stroke-width="1" />

          <!-- Central Junction Nexus Emblem -->
          <text x="500" y="295" text-anchor="middle" fill="rgba(229,184,105,0.2)" font-family="Cinzel Decorative" font-size="28" font-weight="bold">THE ASTRAL CONVERGENCE</text>
          <text x="500" y="318" text-anchor="middle" fill="rgba(244,114,182,0.25)" font-family="Noto Serif JP" font-size="14">天界九界接続大結界</text>

          <!-- 1. The Solar-Bifröst Radial Track (Asgard ➔ Himinbjörg ➔ Convergence ➔ Takamagahara ➔ Izumo) -->
          <g id="line-bifrost">
            <path d="M 120,120 L 260,180 L 500,260 L 740,160 L 880,100" fill="none" stroke="url(#bifrostGradient)" stroke-width="5" stroke-linecap="round" filter="url(#glow)" />
            <!-- Pulsing Transit Light on Track -->
            <circle r="4.5" fill="#FFFFFF">
              <animateMotion path="M 120,120 L 260,180 L 500,260 L 740,160 L 880,100" dur="8s" repeatCount="indefinite" />
            </circle>
          </g>

          <!-- 2. Thor-Susanoo Storm Vanguard Track (Nidavellir ➔ Midgard ➔ Convergence ➔ Fuji ➔ Fushimi) -->
          <g id="line-thunder">
            <path d="M 100,320 L 300,310 L 500,260 L 700,330 L 900,320" fill="none" stroke="url(#stormGradient)" stroke-width="4.5" stroke-linecap="round" stroke-dasharray="9,3" />
            <circle r="4.5" fill="#FCE7A1">
              <animateMotion path="M 100,320 L 300,310 L 500,260 L 700,330 L 900,320" dur="6s" repeatCount="indefinite" />
            </circle>
          </g>

          <!-- 3. Hel-Yomi Underworld Deep Line (Helheim ➔ Niflheim ➔ Ryugu ➔ Yomi) -->
          <g id="line-underworld">
            <path d="M 160,490 L 340,460 L 500,430 L 680,470 L 860,510" fill="none" stroke="url(#underworldGradient)" stroke-width="4" stroke-linecap="round" />
            <circle r="4" fill="#C084FC">
              <animateMotion path="M 160,490 L 340,460 L 500,430 L 680,470 L 860,510" dur="11s" repeatCount="indefinite" />
            </circle>
          </g>

          <!-- 4. Yggdrasil Vertical Trunk Spine (Vanaheim to Jotunheim) -->
          <g id="line-spine">
            <path d="M 500,70 L 500,260 L 500,430 L 500,530" fill="none" stroke="#22C55E" stroke-width="2.5" stroke-dasharray="4,4" opacity="0.6" />
          </g>

          <!-- ================= STATION NODES ================= -->

          <!-- 1. VALHALLA CENTRAL (Top Left) -->
          <g class="station-node cursor-pointer group" onclick="showStationModal('valhalla')">
            <circle cx="120" cy="120" r="16" fill="#131728" stroke="#F59E0B" stroke-width="3" />
            <circle cx="120" cy="120" r="8" fill="#F59E0B" />
            <text x="120" y="92" text-anchor="middle" fill="#FFFFFF" font-family="Cinzel" font-size="13" font-weight="bold">VALHALLA CENTRAL</text>
            <text x="120" y="150" text-anchor="middle" fill="#94A3B8" font-family="sans-serif" font-size="10">Asgard Hub • Odin</text>
          </g>

          <!-- 2. HIMINBJÖRG BIFRÖST TERMINAL -->
          <g class="station-node cursor-pointer group" onclick="showStationModal('himinbjorg')">
            <circle cx="260" cy="180" r="13" fill="#131728" stroke="#00F0FF" stroke-width="3" />
            <circle cx="260" cy="180" r="6" fill="#00F0FF" />
            <text x="260" y="160" text-anchor="middle" fill="#38BDF8" font-family="Cinzel" font-size="11" font-weight="bold">Himinbjörg</text>
            <text x="260" y="205" text-anchor="middle" fill="#94A3B8" font-family="sans-serif" font-size="9">Heimdall's Gate</text>
          </g>

          <!-- 3. CENTRAL CONVERGENCE NEXUS (Inter-Pantheon Gateway) -->
          <g class="station-node cursor-pointer group" onclick="showStationModal('convergence')">
            <circle cx="500" cy="260" r="22" fill="#0B0D18" stroke="#D93829" stroke-width="4" />
            <circle cx="500" cy="260" r="11" fill="#FCE7A1" class="animate-pulse" />
            <text x="500" y="228" text-anchor="middle" fill="#FDE047" font-family="Cinzel Decorative" font-size="13" font-weight="bold">THE GREAT CELESTIAL TORII</text>
            <text x="500" y="242" text-anchor="middle" fill="#F472B6" font-family="Noto Serif JP" font-size="10">天界大鳥居・境界交差点</text>
          </g>

          <!-- 4. TAKAMAGAHARA HIGH SPIRE (Top Right) -->
          <g class="station-node cursor-pointer group" onclick="showStationModal('takamagahara')">
            <circle cx="740" cy="160" r="15" fill="#131728" stroke="#D93829" stroke-width="3" />
            <circle cx="740" cy="160" r="8" fill="#EF4444" />
            <text x="740" y="132" text-anchor="middle" fill="#FFFFFF" font-family="Cinzel" font-size="13" font-weight="bold">TAKAMAGAHARA</text>
            <text x="740" y="190" text-anchor="middle" fill="#F472B6" font-family="Noto Serif JP" font-size="10">高天原 (Amaterasu)</text>
          </g>

          <!-- 5. IZUMO GRAND JUNCTION -->
          <g class="station-node cursor-pointer group" onclick="showStationModal('izumo')">
            <circle cx="880" cy="100" r="14" fill="#131728" stroke="#F59E0B" stroke-width="2.5" />
            <circle cx="880" cy="100" r="6" fill="#F59E0B" />
            <text x="880" y="78" text-anchor="middle" fill="#FFFFFF" font-family="Cinzel" font-size="11" font-weight="bold">Izumo Grand</text>
            <text x="880" y="125" text-anchor="middle" fill="#94A3B8" font-family="Noto Serif JP" font-size="9">出雲結び (Ōkuninushi)</text>
          </g>

          <!-- 6. NIDAVELLIR DEEP FORGE -->
          <g class="station-node cursor-pointer group" onclick="showStationModal('nidavellir')">
            <circle cx="100" cy="320" r="13" fill="#131728" stroke="#F97316" stroke-width="2.5" />
            <circle cx="100" cy="320" r="6" fill="#F97316" />
            <text x="100" y="300" text-anchor="middle" fill="#FED7AA" font-family="Cinzel" font-size="11" font-weight="bold">Niðavellir</text>
            <text x="100" y="345" text-anchor="middle" fill="#94A3B8" font-family="sans-serif" font-size="9">Dwarven Forges</text>
          </g>

          <!-- 7. MIDGARD HARBOR PLATFORM -->
          <g class="station-node cursor-pointer group" onclick="showStationModal('midgard')">
            <circle cx="300" cy="310" r="12" fill="#131728" stroke="#38BDF8" stroke-width="2.5" />
            <circle cx="300" cy="310" r="5" fill="#38BDF8" />
            <text x="300" y="290" text-anchor="middle" fill="#BAE6FD" font-family="Cinzel" font-size="11" font-weight="bold">Midgard Port</text>
            <text x="300" y="335" text-anchor="middle" fill="#94A3B8" font-family="sans-serif" font-size="9">Mortal Realm Access</text>
          </g>

          <!-- 8. MOUNT FUJI TENGU CREST -->
          <g class="station-node cursor-pointer group" onclick="showStationModal('fuji')">
            <circle cx="700" cy="330" r="13" fill="#131728" stroke="#E2E8F0" stroke-width="2.5" />
            <circle cx="700" cy="330" r="6" fill="#FFFFFF" />
            <text x="700" y="310" text-anchor="middle" fill="#FFFFFF" font-family="Cinzel" font-size="11" font-weight="bold">Mt. Fuji Zenith</text>
            <text x="700" y="355" text-anchor="middle" fill="#94A3B8" font-family="Noto Serif JP" font-size="9">富士山頂 (Konohana)</text>
          </g>

          <!-- 9. FUSHIMI INARI VERMILION HUB -->
          <g class="station-node cursor-pointer group" onclick="showStationModal('fushimi')">
            <circle cx="900" cy="320" r="15" fill="#131728" stroke="#D93829" stroke-width="3" />
            <circle cx="900" cy="320" r="7" fill="#EF4444" />
            <text x="900" y="300" text-anchor="middle" fill="#FCA5A5" font-family="Cinzel" font-size="11" font-weight="bold">Fushimi Fox Loop</text>
            <text x="900" y="348" text-anchor="middle" fill="#F472B6" font-family="Noto Serif JP" font-size="9">伏見稲荷 (10,000 Gates)</text>
          </g>

          <!-- 10. HELHEIM UNDERWORLD TERMINUS (Bottom Left) -->
          <g class="station-node cursor-pointer group" onclick="showStationModal('helheim')">
            <circle cx="160" cy="490" r="14" fill="#0A0B12" stroke="#64748B" stroke-width="2.5" />
            <circle cx="160" cy="490" r="6" fill="#94A3B8" />
            <text x="160" y="470" text-anchor="middle" fill="#CBD5E1" font-family="Cinzel" font-size="11" font-weight="bold">Helheim Depths</text>
            <text x="160" y="515" text-anchor="middle" fill="#64748B" font-family="sans-serif" font-size="9">Goddess Hel Realm</text>
          </g>

          <!-- 11. RYŪGŪ-JŌ ABYSSAL PALACE -->
          <g class="station-node cursor-pointer group" onclick="showStationModal('ryugu')">
            <circle cx="500" cy="430" r="14" fill="#0A0B12" stroke="#06B6D4" stroke-width="2.5" />
            <circle cx="500" cy="430" r="6" fill="#22D3EE" />
            <text x="500" y="410" text-anchor="middle" fill="#A5F3FC" font-family="Cinzel" font-size="11" font-weight="bold">Ryūgū-jō Palace</text>
            <text x="500" y="458" text-anchor="middle" fill="#67E8F9" font-family="Noto Serif JP" font-size="9">竜宮城 (Dragon God Watatsumi)</text>
          </g>

          <!-- 12. YOMI-NO-KUNI UNDERWORLD GATE (Bottom Right) -->
          <g class="station-node cursor-pointer group" onclick="showStationModal('yomi')">
            <circle cx="860" cy="510" r="14" fill="#0A0B12" stroke="#7C3AED" stroke-width="2.5" />
            <circle cx="860" cy="510" r="6" fill="#A855F7" />
            <text x="860" y="490" text-anchor="middle" fill="#E9D5FF" font-family="Cinzel" font-size="11" font-weight="bold">Yomi-no-Kuni Gate</text>
            <text x="860" y="535" text-anchor="middle" fill="#A855F7" font-family="Noto Serif JP" font-size="9">黄泉の国 (Izanami Terminus)</text>
          </g>

          <!-- Top Branch: Vanaheim -->
          <g class="station-node cursor-pointer group" onclick="showStationModal('vanaheim')">
            <circle cx="500" cy="70" r="13" fill="#131728" stroke="#10B981" stroke-width="2.5" />
            <circle cx="500" cy="70" r="6" fill="#34D399" />
            <text x="500" y="50" text-anchor="middle" fill="#A7F3D0" font-family="Cinzel" font-size="11" font-weight="bold">Vanaheim Sanctum</text>
            <text x="500" y="95" text-anchor="middle" fill="#6EE7B7" font-family="sans-serif" font-size="9">Freya & Fertility Groves</text>
          </g>

        </svg>

        <!-- Live Legend Float -->
        <div class="absolute bottom-3 left-3 bg-astral/90 border border-slate-800 p-3 rounded-xl backdrop-blur-md text-[11px] space-y-1.5 hidden sm:block">
          <div class="font-cinzel text-divine-gold font-bold text-[10px] uppercase tracking-wider">Legend & Line Keys</div>
          <div class="flex items-center gap-2 text-slate-300">
            <span class="w-3 h-1 rounded-full bg-gradient-to-r from-cyan-400 to-pink-500"></span>
            <span>Bifröst Prismatic Radial</span>
          </div>
          <div class="flex items-center gap-2 text-slate-300">
            <span class="w-3 h-1 rounded-full bg-amber-400"></span>
            <span>Storm & Thunder Express</span>
          </div>
          <div class="flex items-center gap-2 text-slate-300">
            <span class="w-3 h-1 rounded-full bg-purple-500"></span>
            <span>Yomi-Helheim Deep Stygian</span>
          </div>
        </div>

      </div>

    </div>

    <!-- Station Details Modal / Flyout Popup -->
    <div id="station-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-void/80 backdrop-blur-md hidden opacity-0 transition-opacity duration-300">
      <div class="relative max-w-lg w-full bg-astral border border-divine-gold/50 rounded-2xl p-6 sm:p-7 shadow-2xl">
        <button onclick="closeStationModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white text-xl">✕</button>

        <div class="flex items-center gap-3 border-b border-divine-gold/20 pb-4 mb-4">
          <div id="modal-station-icon" class="text-3xl p-2 rounded-xl bg-void border border-slate-700">⛩️</div>
          <div>
            <h3 id="modal-station-title" class="font-cinzelDeco font-bold text-lg text-white">Valhalla Central</h3>
            <p id="modal-station-jp" class="text-xs font-jp text-divine-gold">ヴァルハラ中央駅 • Odin's Great Hall</p>
          </div>
        </div>

        <p id="modal-station-desc" class="text-xs sm:text-sm text-slate-300 leading-relaxed mb-4">
          The supreme station of Asgard, vaulted with gilded shields and spear shafts. Home of the Einherjar warriors and departure hub for the Grand Bifröst Super-Express.
        </p>

        <div class="grid grid-cols-2 gap-3 text-xs mb-5">
          <div class="p-3 rounded-lg bg-void/70 border border-slate-800">
            <span class="text-[10px] text-slate-400 font-mono uppercase">Realm Realm Temperature</span>
            <div id="modal-station-weather" class="font-bold text-amber-300 mt-0.5">Mild Auroral • 18°C</div>
          </div>
          <div class="p-3 rounded-lg bg-void/70 border border-slate-800">
            <span class="text-[10px] text-slate-400 font-mono uppercase">Patron Kami / Deity</span>
            <div id="modal-station-deity" class="font-bold text-cyan-300 mt-0.5">Odin Allfather (ᚩᛞᛁᚾ)</div>
          </div>
        </div>

        <div class="border-t border-slate-800 pt-3 flex items-center justify-between">
          <span class="text-xs text-slate-400">Next Service in: <strong class="text-white font-mono">4 minutes</strong></span>
          <button id="modal-book-btn" class="px-4 py-2 rounded-lg bg-vermilion hover:bg-vermilion-glow text-white text-xs font-cinzel font-bold tracking-wider uppercase transition-all shadow-md">
            Route To Here
          </button>
        </div>
      </div>
    </div>

  </section>

  <!-- LIVE DEPARTURES TIMETABLE BOARD (Split-Flap Mythic Board) -->
  <section id="departures" class="relative z-20 py-20 bg-astral/70 border-y border-divine-gold/20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

      <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
        <div>
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-vermilion/10 border border-vermilion/30 text-vermilion text-xs font-semibold tracking-wider uppercase mb-2">
            <span>発着電光掲示板</span> • CELESTIAL SPLIT-FLAP BOARD
          </div>
          <h2 class="text-3xl font-cinzelDeco font-bold tracking-wide text-white">
            ACTIVE REALM DEPARTURES
          </h2>
          <p class="text-slate-400 text-xs sm:text-sm mt-1">
            Real-time track dispatches, Bifröst bridge clearances, and divine weather alerts.
          </p>
        </div>

        <!-- Terminal Filter & Refresh -->
        <div class="flex items-center gap-3">
          <button id="refresh-board-btn" class="px-3.5 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs text-divine-gold font-mono border border-divine-gold/30 flex items-center gap-2 active:scale-95 transition-all">
            <span>⟳</span>
            <span>Refresh Telemetry</span>
          </button>
        </div>
      </div>

      <!-- Departures Table Wrapper -->
      <div class="rounded-2xl bg-void border border-divine-gold/30 overflow-hidden shadow-2xl">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs font-mono">

            <!-- Table Header -->
            <thead class="bg-[#0C0F1D] text-slate-400 border-b border-divine-gold/20 uppercase tracking-wider text-[11px]">
              <tr>
                <th scope="col" class="py-4 px-4 sm:px-6">Train / Service</th>
                <th scope="col" class="py-4 px-4">Origin ➔ Destination</th>
                <th scope="col" class="py-4 px-4">Departure</th>
                <th scope="col" class="py-4 px-4">Platform</th>
                <th scope="col" class="py-4 px-4">Conductor Deity</th>
                <th scope="col" class="py-4 px-4 sm:px-6 text-right">Gate Status</th>
              </tr>
            </thead>

            <!-- Table Body with Dynamic Mythic Trains -->
            <tbody id="departures-table-body" class="divide-y divide-slate-800/60 text-slate-300">

              <tr class="hover:bg-slate-900/60 transition-colors">
                <td class="py-4 px-4 sm:px-6 font-bold text-white flex items-center gap-2.5">
                  <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                  <div>
                    <span class="text-amber-400">AMTR-001</span>
                    <div class="text-[10px] text-slate-400 font-sans font-normal">Amaterasu Sun-Chariot Super-Express</div>
                  </div>
                </td>
                <td class="py-4 px-4">
                  <div class="font-semibold text-slate-200">Valhalla Central ➔ Takamagahara</div>
                  <div class="text-[10px] text-cyan-400 font-jp">ヴァルハラ ➔ 高天原（光速軌道）</div>
                </td>
                <td class="py-4 px-4 font-bold text-divine-gold">19:50</td>
                <td class="py-4 px-4">
                  <span class="px-2 py-1 rounded bg-slate-800 text-slate-200 border border-slate-700">Track 07-☀️</span>
                </td>
                <td class="py-4 px-4 text-sakura">Amaterasu-Ōmikami</td>
                <td class="py-4 px-4 sm:px-6 text-right">
                  <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-950 text-emerald-400 border border-emerald-500/40">
                    ON TIME • BOARDING
                  </span>
                </td>
              </tr>

              <tr class="hover:bg-slate-900/60 transition-colors">
                <td class="py-4 px-4 sm:px-6 font-bold text-white flex items-center gap-2.5">
                  <span class="w-2.5 h-2.5 rounded-full bg-cyan-400"></span>
                  <div>
                    <span class="text-cyan-400">SLEIP-888</span>
                    <div class="text-[10px] text-slate-400 font-sans font-normal">Sleipnir Eight-Bogie Maglev Bullet</div>
                  </div>
                </td>
                <td class="py-4 px-4">
                  <div class="font-semibold text-slate-200">Himinbjörg ➔ Izumo Grand Nexus</div>
                  <div class="text-[10px] text-cyan-400 font-jp">虹橋端 ➔ 出雲大社（神風結び）</div>
                </td>
                <td class="py-4 px-4 font-bold text-divine-gold">20:05</td>
                <td class="py-4 px-4">
                  <span class="px-2 py-1 rounded bg-slate-800 text-slate-200 border border-slate-700">Track 01-ᛋ</span>
                </td>
                <td class="py-4 px-4 text-cyan-300">Odin & Hugin Raven</td>
                <td class="py-4 px-4 sm:px-6 text-right">
                  <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-cyan-950 text-cyan-300 border border-cyan-500/40">
                    CLEAR BRIDGE
                  </span>
                </td>
              </tr>

              <tr class="hover:bg-slate-900/60 transition-colors">
                <td class="py-4 px-4 sm:px-6 font-bold text-white flex items-center gap-2.5">
                  <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                  <div>
                    <span class="text-amber-300">MJOL-X9</span>
                    <div class="text-[10px] text-slate-400 font-sans font-normal">Thor Thunder-Plow Heavy Locomotive</div>
                  </div>
                </td>
                <td class="py-4 px-4">
                  <div class="font-semibold text-slate-200">Niðavellir Forges ➔ Mt. Fuji Zenith</div>
                  <div class="text-[10px] text-amber-300 font-jp">鍛冶界 ➔ 富士山天狗嶺</div>
                </td>
                <td class="py-4 px-4 font-bold text-divine-gold">20:20</td>
                <td class="py-4 px-4">
                  <span class="px-2 py-1 rounded bg-slate-800 text-slate-200 border border-slate-700">Track 04-⚡</span>
                </td>
                <td class="py-4 px-4 text-amber-400">Thor & Tanngrisnir</td>
                <td class="py-4 px-4 sm:px-6 text-right">
                  <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-950 text-amber-300 border border-amber-500/40">
                    LIGHTNING PRE-CHARGE
                  </span>
                </td>
              </tr>

              <tr class="hover:bg-slate-900/60 transition-colors">
                <td class="py-4 px-4 sm:px-6 font-bold text-white flex items-center gap-2.5">
                  <span class="w-2.5 h-2.5 rounded-full bg-vermilion"></span>
                  <div>
                    <span class="text-vermilion-glow">KITS-007</span>
                    <div class="text-[10px] text-slate-400 font-sans font-normal">Inari Foxfire Vermilion Nightliner</div>
                  </div>
                </td>
                <td class="py-4 px-4">
                  <div class="font-semibold text-slate-200">Fushimi Fox Loop ➔ Vanaheim Sanctum</div>
                  <div class="text-[10px] text-pink-400 font-jp">伏見稲荷 ➔ 豊穣界ヴァナヘイム</div>
                </td>
                <td class="py-4 px-4 font-bold text-divine-gold">20:45</td>
                <td class="py-4 px-4">
                  <span class="px-2 py-1 rounded bg-slate-800 text-slate-200 border border-slate-700">Track 09-🦊</span>
                </td>
                <td class="py-4 px-4 text-pink-300">Inari & White Fox Attendants</td>
                <td class="py-4 px-4 sm:px-6 text-right">
                  <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-950 text-emerald-400 border border-emerald-500/40">
                    ON TIME • TEA SERVED
                  </span>
                </td>
              </tr>

              <tr class="hover:bg-slate-900/60 transition-colors">
                <td class="py-4 px-4 sm:px-6 font-bold text-white flex items-center gap-2.5">
                  <span class="w-2.5 h-2.5 rounded-full bg-purple-400"></span>
                  <div>
                    <span class="text-purple-300">YOMI-666</span>
                    <div class="text-[10px] text-slate-400 font-sans font-normal">Stygian Stasis Sleeper (Izanami/Hel)</div>
                  </div>
                </td>
                <td class="py-4 px-4">
                  <div class="font-semibold text-slate-200">Ryūgū-jō Palace ➔ Helheim Depths</div>
                  <div class="text-[10px] text-purple-400 font-jp">竜宮海底 ➔ ヘルヘイム冥界</div>
                </td>
                <td class="py-4 px-4 font-bold text-divine-gold">21:10</td>
                <td class="py-4 px-4">
                  <span class="px-2 py-1 rounded bg-slate-800 text-slate-200 border border-slate-700">Track 00-🌑</span>
                </td>
                <td class="py-4 px-4 text-purple-400">Goddess Hel & Izanami</td>
                <td class="py-4 px-4 sm:px-6 text-right">
                  <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-purple-950 text-purple-300 border border-purple-500/40">
                    DEEP UNDERWORLD GATE
                  </span>
                </td>
              </tr>

            </tbody>

          </table>
        </div>
      </div>

    </div>
  </section>

  <!-- FLEET SHOWCASE: MYTHIC LOCOMOTIVES -->
  <section id="fleet" class="relative z-20 py-24 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">

    <div class="text-center max-w-3xl mx-auto mb-14">
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs font-semibold tracking-wider uppercase mb-3">
        <span>神機列車図鑑</span> • SACRED LOCOMOTIVE ROSTER
      </div>
      <h2 class="text-3xl sm:text-4xl font-cinzelDeco font-bold tracking-wide text-white">
        THE INTER-PANTHEON FLEET
      </h2>
      <p class="mt-3 text-slate-400 text-sm">
        Forged by Ivaldi's Dwarven master blacksmiths and tempered in the sacred flames of Mount Fuji. Discover our divine locomotives.
      </p>
    </div>

    <!-- Fleet Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">

      <!-- Card 1: Sleipnir-VIII Maglev -->
      <div class="group relative rounded-2xl bg-void/90 border border-divine-gold/30 p-6 flex flex-col justify-between hover:border-cyan-400 transition-all duration-500 shadow-xl overflow-hidden hover:-translate-y-2">
        <div class="absolute top-0 right-0 w-32 h-32 bg-cyan-500/10 rounded-full blur-2xl pointer-events-none group-hover:bg-cyan-500/20"></div>

        <div>
          <!-- Visual Badge & Type -->
          <div class="flex items-center justify-between mb-4">
            <span class="px-2.5 py-1 rounded text-[10px] font-mono font-bold bg-cyan-950 text-cyan-300 border border-cyan-500/40">
              ÆSIR SERIES 800
            </span>
            <span class="text-2xl">🐎</span>
          </div>

          <h3 class="font-cinzelDeco font-bold text-xl text-white group-hover:text-cyan-300 transition-colors">
            SLEIPNIR MARK VIII
          </h3>
          <p class="font-jp text-xs text-slate-400 mb-4">八脚電磁浮上特急 • Allfather Flagship</p>

          <p class="text-xs text-slate-300 leading-relaxed mb-5">
            Features eight articulating electromagnetic bogies styled after Odin’s eight-legged steed. Surges along Bifröst prism light rails with zero friction and temporal stabilizers.
          </p>

          <!-- Locomotive Specs -->
          <div class="space-y-2.5 text-xs font-mono border-t border-slate-800 pt-4">
            <div class="flex justify-between text-slate-400">
              <span>Velocity:</span>
              <strong class="text-white">Mach 5.1 (Realm-Warp)</strong>
            </div>
            <div class="flex justify-between text-slate-400">
              <span>Powerplant:</span>
              <strong class="text-cyan-300">Gungnir Resonance Core</strong>
            </div>
            <div class="flex justify-between text-slate-400">
              <span>Onboard Luxury:</span>
              <strong class="text-slate-200">Mead Lounge & Raven Comms</strong>
            </div>
          </div>
        </div>

        <button onclick="selectTrainFleet('valhalla', 'takamagahara')" class="mt-6 w-full py-2.5 rounded-xl bg-slate-800 hover:bg-cyan-900/40 text-cyan-300 border border-cyan-500/30 text-xs font-cinzel font-semibold tracking-wider transition-colors">
          Book Sleipnir Line
        </button>
      </div>

      <!-- Card 2: Amaterasu Solar Dragon Series 9000 -->
      <div class="group relative rounded-2xl bg-void/90 border border-divine-gold/50 p-6 flex flex-col justify-between hover:border-vermilion transition-all duration-500 shadow-xl overflow-hidden hover:-translate-y-2 ring-1 ring-amber-500/20">
        <div class="absolute top-0 right-0 w-32 h-32 bg-vermilion/15 rounded-full blur-2xl pointer-events-none group-hover:bg-vermilion/30"></div>

        <div>
          <!-- Visual Badge & Type -->
          <div class="flex items-center justify-between mb-4">
            <span class="px-2.5 py-1 rounded text-[10px] font-mono font-bold bg-vermilion-deep text-amber-200 border border-vermilion">
              SHINTO IMPERIAL SUPREME
            </span>
            <span class="text-2xl">🐉</span>
          </div>

          <h3 class="font-cinzelDeco font-bold text-xl text-white group-hover:text-amber-300 transition-colors">
            YAMATA-MURAKUMO 9000
          </h3>
          <p class="font-jp text-xs text-sakura mb-4">天叢雲剣・八岐太陽新幹線</p>

          <p class="text-xs text-slate-300 leading-relaxed mb-5">
            Aerodynamic crimson and gold leaf Shinkansen crafted with sacred titanium. Features the Heavenly Mirror (Yata-no-Kagami) beam headlights capable of illuminating the darkest abyss of Niflheim.
          </p>

          <!-- Locomotive Specs -->
          <div class="space-y-2.5 text-xs font-mono border-t border-slate-800 pt-4">
            <div class="flex justify-between text-slate-400">
              <span>Velocity:</span>
              <strong class="text-white">Mach 6.2 (Solar Beam)</strong>
            </div>
            <div class="flex justify-between text-slate-400">
              <span>Powerplant:</span>
              <strong class="text-amber-300">Amaterasu Sun-Pearl Cell</strong>
            </div>
            <div class="flex justify-between text-slate-400">
              <span>Onboard Luxury:</span>
              <strong class="text-slate-200">Tatami Suites & Kagura Chamber</strong>
            </div>
          </div>
        </div>

        <button onclick="selectTrainFleet('himinbjorg', 'izumo')" class="mt-6 w-full py-2.5 rounded-xl bg-vermilion hover:bg-vermilion-glow text-white text-xs font-cinzel font-semibold tracking-wider transition-colors shadow-lg shadow-vermilion/30">
          Book Yamata Express
        </button>
      </div>

      <!-- Card 3: Inari Foxfire Nightliner -->
      <div class="group relative rounded-2xl bg-void/90 border border-divine-gold/30 p-6 flex flex-col justify-between hover:border-pink-500 transition-all duration-500 shadow-xl overflow-hidden hover:-translate-y-2">
        <div class="absolute top-0 right-0 w-32 h-32 bg-pink-500/10 rounded-full blur-2xl pointer-events-none group-hover:bg-pink-500/20"></div>

        <div>
          <!-- Visual Badge & Type -->
          <div class="flex items-center justify-between mb-4">
            <span class="px-2.5 py-1 rounded text-[10px] font-mono font-bold bg-pink-950 text-pink-300 border border-pink-500/40">
              ENMUSUBI SLEEPER
            </span>
            <span class="text-2xl">🦊</span>
          </div>

          <h3 class="font-cinzelDeco font-bold text-xl text-white group-hover:text-pink-300 transition-colors">
            KITSUNE FIRE STAR-07
          </h3>
          <p class="font-jp text-xs text-slate-400 mb-4">狐火寝台夜行列車 • Fox Goddess Liner</p>

          <p class="text-xs text-slate-300 leading-relaxed mb-5">
            The beloved nighttime cruise train illuminating the void with thousands of levitating foxfire lanterns. Features heated cedar onsen baths and infinite sake barrels blessed by Freya and Inari.
          </p>

          <!-- Locomotive Specs -->
          <div class="space-y-2.5 text-xs font-mono border-t border-slate-800 pt-4">
            <div class="flex justify-between text-slate-400">
              <span>Velocity:</span>
              <strong class="text-white">Mach 3.8 (Tranquil Cruise)</strong>
            </div>
            <div class="flex justify-between text-slate-400">
              <span>Powerplant:</span>
              <strong class="text-pink-400">Spirit Foxfire Hearth</strong>
            </div>
            <div class="flex justify-between text-slate-400">
              <span>Onboard Luxury:</span>
              <strong class="text-slate-200">Hinoki Bath & Sweet Mochi Feast</strong>
            </div>
          </div>
        </div>

        <button onclick="selectTrainFleet('fushimi', 'vanaheim')" class="mt-6 w-full py-2.5 rounded-xl bg-slate-800 hover:bg-pink-900/40 text-pink-300 border border-pink-500/30 text-xs font-cinzel font-semibold tracking-wider transition-colors">
          Book Kitsune Nightliner
        </button>
      </div>

    </div>

  </section>

  <!-- INTERACTIVE DEITY ORACLE & CONCIERGE (Mímir & Omoikane) -->
  <section id="oracle" class="relative z-20 py-20 bg-astral/80 border-t border-divine-gold/20">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

      <div class="text-center mb-10">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-cyan-500/10 border border-cyan-500/30 text-cyan-300 text-xs font-semibold tracking-wider uppercase mb-3">
          <span>知恵の泉・神思兼神</span> • WISDOM DUAL ORACLE
        </div>
        <h2 class="text-3xl font-cinzelDeco font-bold tracking-wide text-white">
          MÍMIR & OMOIKANE TRANSIT CONCIERGE
        </h2>
        <p class="mt-2 text-slate-400 text-sm">
          Have questions regarding customs clearance between Asgard and Takamagahara? Ask the decapitated well of Mímir and the Shinto deity of collective intellect.
        </p>
      </div>

      <!-- Interactive Concierge Terminal -->
      <div class="rounded-2xl bg-void border border-divine-gold/40 shadow-2xl p-6 sm:p-8">

        <!-- Quick Question Chips -->
        <div class="mb-5">
          <div class="text-xs text-slate-400 mb-2 font-mono">POPULAR ORACLE INQUIRIES:</div>
          <div class="flex flex-wrap gap-2">
            <button onclick="askOracle('baggage')" class="px-3 py-1.5 rounded-lg bg-slate-800/80 hover:bg-slate-700 text-xs text-slate-300 hover:text-white border border-slate-700 transition-all">
              ⚔️ Can I bring Mjölnir or Muramasa onboard?
            </button>
            <button onclick="askOracle('dining')" class="px-3 py-1.5 rounded-lg bg-slate-800/80 hover:bg-slate-700 text-xs text-slate-300 hover:text-white border border-slate-700 transition-all">
              🍱 Mead of Poetry vs. Takama Ambrosia Bento?
            </button>
            <button onclick="askOracle('yomi')" class="px-3 py-1.5 rounded-lg bg-slate-800/80 hover:bg-slate-700 text-xs text-slate-300 hover:text-white border border-slate-700 transition-all">
              🌑 Do I need a return talisman from Helheim / Yomi?
            </button>
            <button onclick="askOracle('heimdall')" class="px-3 py-1.5 rounded-lg bg-slate-800/80 hover:bg-slate-700 text-xs text-slate-300 hover:text-white border border-slate-700 transition-all">
              🎺 What if Heimdall sounds the Gjallarhorn during transit?
            </button>
          </div>
        </div>

        <!-- Chat Conversation Display -->
        <div id="oracle-chat-box" class="h-64 overflow-y-auto space-y-4 p-4 rounded-xl bg-slate-950/70 border border-slate-800 text-xs sm:text-sm">

          <!-- Default Welcome from Dual Oracle -->
          <div class="flex gap-3">
            <div class="w-8 h-8 rounded-full bg-cyan-900 border border-cyan-400 flex items-center justify-center text-base shrink-0">
              ᛗ
            </div>
            <div class="bg-slate-900 p-3.5 rounded-2xl rounded-tl-none border border-slate-800 max-w-xl">
              <div class="font-cinzel font-bold text-xs text-cyan-300 mb-1">Mímir, Head of the Well & Omoikane no Kami</div>
              <p class="text-slate-300 leading-relaxed">
                Hail, weary pilgrim of the world-strands. Whether your journey takes you toward the golden benches of Valhalla or the tranquil rice plains of Mizuho, we hold counsel on all celestial railway bylaws. What truth do you seek?
              </p>
            </div>
          </div>

        </div>

        <!-- Custom Input Field -->
        <form id="oracle-form" class="mt-4 flex gap-2" onsubmit="handleOracleSubmit(event)">
          <input id="oracle-input" type="text" placeholder="Inquire about realm customs, baggage laws, or mythic transfers..." class="flex-1 bg-astral border border-slate-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-divine-gold/50">
          <button type="submit" class="px-6 py-3 rounded-xl bg-divine-gold hover:bg-divine-shimmer text-slate-950 font-cinzel font-bold text-xs tracking-wider uppercase transition-all shadow-md active:scale-95">
            Consult Oracle
          </button>
        </form>

      </div>

    </div>
  </section>

  <!-- PANTHEON TESTIMONIALS / SACRED LORE DIARIES -->
  <section class="relative z-20 py-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
    <div class="text-center max-w-3xl mx-auto mb-12">
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-vermilion/10 border border-vermilion/30 text-vermilion text-xs font-semibold tracking-wider uppercase mb-3">
        <span>旅客の声</span> • TALES FROM THE CARRIAGES
      </div>
      <h2 class="text-3xl font-cinzelDeco font-bold tracking-wide text-white">
        PILGRIM PASSENGER LOGS
      </h2>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

      <div class="p-6 rounded-2xl bg-void/80 border border-divine-gold/20 flex flex-col justify-between">
        <div>
          <div class="flex items-center gap-1 text-amber-400 mb-3 text-xs">
            ★★★★★ <span class="text-slate-400 ml-2 font-mono">1st Imperial Class</span>
          </div>
          <p class="text-slate-300 text-xs sm:text-sm italic leading-relaxed">
            "Transiting from Midgard to Takamagahara used to take nine days of ritual purification. On the Yamata-Murakumo Shinkansen, Heimdall cleared my baggage in three seconds and the Inari fox attendants served roasted chestnuts and warm mead."
          </p>
        </div>
        <div class="mt-5 flex items-center gap-3 border-t border-slate-800 pt-3">
          <div class="w-8 h-8 rounded-full bg-vermilion flex items-center justify-center font-bold text-xs text-white">武</div>
          <div>
            <div class="font-bold text-xs text-slate-200">Lord Minamoto of Kyoto</div>
            <div class="text-[10px] text-slate-400">Samurai Pilgrim Envoy</div>
          </div>
        </div>
      </div>

      <div class="p-6 rounded-2xl bg-void/80 border border-divine-gold/20 flex flex-col justify-between">
        <div>
          <div class="flex items-center gap-1 text-cyan-400 mb-3 text-xs">
            ★★★★★ <span class="text-slate-400 ml-2 font-mono">Valkyrie Envoy Suite</span>
          </div>
          <p class="text-slate-300 text-xs sm:text-sm italic leading-relaxed">
            "The magnetic suspension over the Bifröst bridge is butter-smooth. Even when Thor provoked a Class-5 thunderstorm over Jötunheim, my drinking horn didn't spill a single drop of golden honey. 10/10."
          </p>
        </div>
        <div class="mt-5 flex items-center gap-3 border-t border-slate-800 pt-3">
          <div class="w-8 h-8 rounded-full bg-cyan-900 flex items-center justify-center font-bold text-xs text-cyan-200 font-cinzel">ᚱ</div>
          <div>
            <div class="font-bold text-xs text-slate-200">Reginleif Shieldmaiden</div>
            <div class="text-[10px] text-slate-400">Valkyrie Dispatch 3rd Wing</div>
          </div>
        </div>
      </div>

      <div class="p-6 rounded-2xl bg-void/80 border border-divine-gold/20 flex flex-col justify-between">
        <div>
          <div class="flex items-center gap-1 text-pink-400 mb-3 text-xs">
            ★★★★★ <span class="text-slate-400 ml-2 font-mono">Kitsune Sleeper Berth</span>
          </div>
          <p class="text-slate-300 text-xs sm:text-sm italic leading-relaxed">
            "The dual wisdom of Mímir and Omoikane at the customer desk saved me from wandering into the Helheim terminal by mistake. The starlight views through the Yggdrasil canopy are truly life-altering."
          </p>
        </div>
        <div class="mt-5 flex items-center gap-3 border-t border-slate-800 pt-3">
          <div class="w-8 h-8 rounded-full bg-pink-950 flex items-center justify-center font-bold text-xs text-pink-300 font-jp">桜</div>
          <div>
            <div class="font-bold text-xs text-slate-200">Kaguya-hime of the Bamboo Grove</div>
            <div class="text-[10px] text-slate-400">Lunar Realm Diplomat</div>
          </div>
        </div>
      </div>

    </div>
  </section>

  <!-- FOOTER: Sacred Covenant & Navigation -->
  <footer class="relative z-20 bg-void border-t border-divine-gold/30 pt-16 pb-12 text-slate-400">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-10 mb-12">

        <div class="space-y-4 md:col-span-1">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-vermilion-deep border border-divine-gold/40 flex items-center justify-center text-amber-200">
              ⛩️
            </div>
            <span class="font-cinzelDeco font-bold text-white text-base">KAMIGAMI & ÆSIR</span>
          </div>
          <p class="text-xs leading-relaxed font-normal text-slate-400">
            The Trans-Dimensional Transit Authority linking Yggdrasil and Takamagahara under the Eternal Concordat of the Two Heavens.
          </p>
          <div class="text-[11px] font-mono text-divine-gold">
            BIFRÖST FREQUENCY: 432.88 THz
          </div>
        </div>

        <div>
          <h3 class="font-cinzel font-bold text-xs text-white uppercase tracking-wider mb-4">Realm Hubs</h3>
          <ul class="space-y-2 text-xs">
            <li><a href="#transit-map" class="hover:text-divine-gold transition-colors">Valhalla Central (Asgard)</a></li>
            <li><a href="#transit-map" class="hover:text-divine-gold transition-colors">Takamagahara High Spire</a></li>
            <li><a href="#transit-map" class="hover:text-divine-gold transition-colors">Himinbjörg Bifröst Bridge</a></li>
            <li><a href="#transit-map" class="hover:text-divine-gold transition-colors">Izumo Grand Intersection</a></li>
            <li><a href="#transit-map" class="hover:text-divine-gold transition-colors">Helheim & Yomi Stygian Gate</a></li>
          </ul>
        </div>

        <div>
          <h3 class="font-cinzel font-bold text-xs text-white uppercase tracking-wider mb-4">Mythic Locomotives</h3>
          <ul class="space-y-2 text-xs">
            <li><a href="#fleet" class="hover:text-cyan-300 transition-colors">Sleipnir Mark VIII Maglev</a></li>
            <li><a href="#fleet" class="hover:text-amber-300 transition-colors">Yamata-Murakumo 9000 Shinkansen</a></li>
            <li><a href="#fleet" class="hover:text-pink-300 transition-colors">Kitsune Fire Star Nightliner</a></li>
            <li><a href="#fleet" class="hover:text-amber-400 transition-colors">Mjölnir Heavy Freight Plow</a></li>
          </ul>
        </div>

        <div>
          <h3 class="font-cinzel font-bold text-xs text-white uppercase tracking-wider mb-4">Divine Bylaws</h3>
          <p class="text-xs text-slate-400 leading-relaxed mb-3">
            All passengers must show valid Vegvísir or Shinto Omamori upon boarding. Weapons must remain sheathed in the Peace of Frigg.
          </p>
          <div class="p-2.5 rounded-lg bg-slate-900 border border-slate-800 text-[10px] text-slate-300 font-jp">
            神仏習合・北欧神話永久交通同盟条約
          </div>
        </div>

      </div>

      <div class="border-t border-slate-800/80 pt-8 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500 gap-4">
        <div>
          © 2026 Kamigami & Æsir Celestial Railways • All Realms Reserved (神界・天界軌道局)
        </div>
        <div class="flex items-center space-x-6 text-[11px]">
          <span class="hover:text-slate-300">Sanctuary Privacy Seal</span>
          <span class="hover:text-slate-300">Bifröst Safety Protocol</span>
          <span class="hover:text-slate-300">Runes & Kanji Glossary</span>
        </div>
      </div>
    </div>
  </footer>

  <!-- JAVASCRIPT: Full Dynamic Interaction Engine -->
  <script>
    // ==========================================
    // 1. CELESTIAL CANVAS: Bifröst Prisms, Runes & Sakura Petals
    // ==========================================
    const canvas = document.getElementById('celestial-canvas');
    const ctx = canvas.getContext('2d');
    let width, height;
    let particles = [];
    const runesList = ['ᚠ', 'ᚢ', 'ᚦ', 'ᚨ', 'ᚱ', 'ᚲ', 'ᚷ', 'ᚹ', 'ᚺ', 'ᚾ', 'ᛁ', 'ᛃ', 'ᛈ', 'ᛇ', 'ᛉ', 'ᛋ', 'ᛏ', 'ᛒ', 'ᛖ', 'ᛗ', 'ᛚ', 'ᛜ', 'ᛞ', 'ᛟ', '神', '天', '光', '雷', '風', '桜', '鳥', '道'];

    function resizeCanvas() {
      width = canvas.width = window.innerWidth;
      height = canvas.height = window.innerHeight;
    }
    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();

    class CelestialParticle {
      constructor() {
        this.reset();
      }
      reset() {
        this.x = Math.random() * width;
        this.y = Math.random() * height;
        this.size = Math.random() * 12 + 6;
        this.speedX = (Math.random() - 0.5) * 0.8 + 0.3; // gentle drift right
        this.speedY = (Math.random() - 0.5) * 0.6 + 0.4; // gentle float down
        this.type = Math.random() > 0.45 ? 'sakura' : (Math.random() > 0.5 ? 'rune' : 'starlight');
        this.runeChar = runesList[Math.floor(Math.random() * runesList.length)];
        this.opacity = Math.random() * 0.5 + 0.2;
        this.rotation = Math.random() * Math.PI * 2;
        this.rotationSpeed = (Math.random() - 0.5) * 0.02;
      }
      update() {
        this.x += this.speedX;
        this.y += this.speedY;
        this.rotation += this.rotationSpeed;
        if (this.x > width + 20 || this.x < -20 || this.y > height + 20) {
          this.reset();
          this.y = -10;
        }
      }
      draw() {
        ctx.save();
        ctx.translate(this.x, this.y);
        ctx.rotate(this.rotation);
        ctx.globalAlpha = this.opacity;

        if (this.type === 'sakura') {
          // Sakura petal
          ctx.fillStyle = '#F472B6';
          ctx.beginPath();
          ctx.moveTo(0, 0);
          ctx.bezierCurveTo(-this.size / 2, -this.size / 2, -this.size / 2, this.size / 2, 0, this.size);
          ctx.bezierCurveTo(this.size / 2, this.size / 2, this.size / 2, -this.size / 2, 0, 0);
          ctx.fill();
        } else if (this.type === 'rune') {
          // Ancient Rune or Kanji
          ctx.font = `${Math.floor(this.size)}px 'Noto Serif JP', 'Cinzel'`;
          ctx.fillStyle = '#E5B869';
          ctx.shadowBlur = 8;
          ctx.shadowColor = '#00F0FF';
          ctx.fillText(this.runeChar, 0, 0);
        } else {
          // Bifröst Astral Spark
          ctx.fillStyle = '#00F0FF';
          ctx.shadowBlur = 6;
          ctx.shadowColor = '#EC4899';
          ctx.beginPath();
          ctx.arc(0, 0, this.size / 4, 0, Math.PI * 2);
          ctx.fill();
        }
        ctx.restore();
      }
    }

    // Initialize 60 ambient particles
    for (let i = 0; i < 65; i++) {
      particles.push(new CelestialParticle());
    }

    function animateCanvas() {
      ctx.clearRect(0, 0, width, height);
      particles.forEach(p => {
        p.update();
        p.draw();
      });
      requestAnimationFrame(animateCanvas);
    }
    animateCanvas();

    // ==========================================
    // 2. CELESTIAL CLOCK (Reiwa-Ragnarök Stardate)
    // ==========================================
    function updateCelestialClock() {
      const now = new Date();
      const pad = n => String(n).padStart(2, '0');
      const timeStr = `${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
      const el = document.getElementById('celestial-clock');
      if (el) {
        el.textContent = `ERA REIWA-RAGNARÖK • 2026.04.${pad(now.getDate())} • ${timeStr} TC`;
      }
    }
    setInterval(updateCelestialClock, 1000);
    updateCelestialClock();

    // ==========================================
    // 3. SOUNDSCAPE SYNTHESIZER (Web Audio API)
    // Pure procedural bells & celestial harmonic drone!
    // ==========================================
    let audioCtx = null;
    let isSoundOn = false;
    let ambientDrone = null;

    function initAudio() {
      if (!audioCtx) {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        audioCtx = new AudioContext();
      }
    }

    function playSanctuaryBell() {
      initAudio();
      if (audioCtx.state === 'suspended') {
        audioCtx.resume();
      }
      // Traditional Japanese Suzu / Temple Singing Bowl Synthesizer
      const now = audioCtx.currentTime;
      const osc1 = audioCtx.createOscillator();
      const osc2 = audioCtx.createOscillator();
      const gain = audioCtx.createGain();

      osc1.type = 'sine';
      osc1.frequency.setValueAtTime(880, now); // A5 Bell
      osc1.frequency.exponentialRampToValueAtTime(440, now + 2.5);

      osc2.type = 'triangle';
      osc2.frequency.setValueAtTime(1760, now);
      osc2.frequency.exponentialRampToValueAtTime(880, now + 1.8);

      gain.gain.setValueAtTime(0.2, now);
      gain.gain.exponentialRampToValueAtTime(0.0001, now + 2.8);

      osc1.connect(gain);
      osc2.connect(gain);
      gain.connect(audioCtx.destination);

      osc1.start(now);
      osc2.start(now);
      osc1.stop(now + 2.9);
      osc2.stop(now + 2.9);
    }

    document.getElementById('soundscape-toggle').addEventListener('click', () => {
      isSoundOn = !isSoundOn;
      const icon = document.getElementById('sound-icon');
      const status = document.getElementById('sound-status');
      if (isSoundOn) {
        playSanctuaryBell();
        icon.textContent = '🎐';
        status.textContent = 'Sanctuary Chime: Active';
      } else {
        icon.textContent = '🔔';
        status.textContent = 'Bell Chime: Off';
      }
    });

    // ==========================================
    // 4. BOOKING ENGINE & TICKET GENERATOR
    // ==========================================
    const originSelect = document.getElementById('origin-select');
    const destSelect = document.getElementById('dest-select');
    const deityBtns = document.querySelectorAll('.deity-btn');
    const travelDate = document.getElementById('travel-date');
    const travelTime = document.getElementById('travel-time');
    const travelClass = document.getElementById('travel-class');
    const passengerName = document.getElementById('passenger-name');
    const sacredTalisman = document.getElementById('sacred-talisman');
    const generateBtn = document.getElementById('generate-ticket-btn');

    let selectedDeity = 'amaterasu';

    // Deity button selection logic
    deityBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        deityBtns.forEach(b => {
          b.classList.remove('active', 'border-divine-gold', 'bg-vermilion/15');
          b.classList.add('border-slate-700', 'bg-void');
        });
        btn.classList.add('active', 'border-divine-gold', 'bg-vermilion/15');
        btn.classList.remove('border-slate-700', 'bg-void');
        selectedDeity = btn.getAttribute('data-deity');
        updateTicketPreview();
      });
    });

    // Station Swap Button
    document.getElementById('swap-stations-btn').addEventListener('click', () => {
      const temp = originSelect.value;
      originSelect.value = destSelect.value;
      destSelect.value = temp;
      updateTicketPreview();
    });

    // Event listeners for real-time ticket preview
    [originSelect, destSelect, travelDate, travelTime, travelClass, passengerName, sacredTalisman].forEach(el => {
      el.addEventListener('change', updateTicketPreview);
      el.addEventListener('input', updateTicketPreview);
    });

    function getStationName(key) {
      const map = {
        valhalla: 'Valhalla Central (Asgard)',
        himinbjorg: 'Himinbjörg Bifröst Terminal',
        jotunheim: 'Jötunheim Frost Spire',
        niflheim: 'Niflheim Mist Platform',
        vanaheim: 'Vanaheim Blooming Groves',
        nidavellir: 'Niðavellir Deep Forge',
        helheim: 'Helheim Underworld Depths',
        takamagahara: 'Takamagahara High Spire',
        izumo: 'Izumo Grand Junction',
        fushimi: 'Fushimi Inari Vermilion Loop',
        tsukuyomi: 'Tsukuyomi Lunar Terminal',
        ryugu: 'Ryūgū-jō Abyssal Water Station',
        yomi: 'Yomi-no-Kuni Gate',
        fuji: 'Mount Fuji Tengu Crest'
      };
      return map[key] || key;
    }

    function calculateFare() {
      let base = 350;
      if (travelClass.value === 'valkyrie') base = 650;
      if (travelClass.value === 'imperial') base = 1200;
      if (originSelect.value === 'helheim' || destSelect.value === 'helheim' || originSelect.value === 'yomi' || destSelect.value === 'yomi') {
        base += 180; // Underworld hazardous toll
      }
      return base;
    }

    function updateTicketPreview() {
      // Fare
      const fare = calculateFare();
      document.getElementById('calculated-fare').textContent = fare;

      // Pass Details
      document.getElementById('pass-origin').textContent = getStationName(originSelect.value).split(' ')[0] + ' ' + (getStationName(originSelect.value).split(' ')[1] || '');
      document.getElementById('pass-dest').textContent = getStationName(destSelect.value).split(' ')[0] + ' ' + (getStationName(destSelect.value).split(' ')[1] || '');
      document.getElementById('pass-name').textContent = passengerName.value || 'Anonymous Astral Voyager';

      // Deity string
      const deityMap = {
        amaterasu: 'Amaterasu ☀️ (Solar Boon)',
        odin: 'Odin Allfather 🦅 (Raven Guide)',
        thor: 'Thor Mjölnir ⚡ (Storm Shield)',
        inari: 'Inari Okami 🦊 (Fox Blessing)'
      };
      document.getElementById('pass-deity').textContent = deityMap[selectedDeity];

      // Date / Time
      document.getElementById('pass-date').textContent = `${travelDate.value} • ${travelTime.value}`;

      // Class badge
      const badgeMap = {
        pilgrim: 'PILGRIM CLASS',
        valkyrie: 'VALKYRIE ENVOY',
        imperial: 'IMPERIAL FIRST SUITE'
      };
      document.getElementById('pass-class-badge').textContent = badgeMap[travelClass.value];

      // Sigil
      const sigilMap = {
        omamori: 'Shinto Omamori (Safe Transit)',
        vegvisir: 'Norse Vegvísir (Wayfinder Rune)',
        aegishjalmur: 'Helm of Awe (Ward of Fear)',
        magatama: 'Imperial Magatama Jade Sigil'
      };
      document.getElementById('pass-sigil').textContent = sigilMap[sacredTalisman.value];

      // Seat with dynamic rune
      const runes = ['ᚠ', 'ᚢ', 'ᚦ', 'ᚨ', 'ᚱ', 'ᚲ', 'ᚷ', 'ᚹ', 'ᛋ', 'ᛏ'];
      const randomRune = runes[Math.floor(Math.random() * runes.length)];
      const randomCar = Math.floor(Math.random() * 8) + 1;
      const randomSeat = Math.floor(Math.random() * 24) + 1;
      document.getElementById('pass-seat').textContent = `Car 0${randomCar} • Seat ${randomSeat}-${randomRune}`;
    }

    generateBtn.addEventListener('click', () => {
      updateTicketPreview();
      if (isSoundOn) playSanctuaryBell();
      const toast = document.getElementById('booking-toast');
      toast.classList.remove('hidden');
      setTimeout(() => {
        toast.classList.add('hidden');
      }, 4000);
    });

    function printTicket() {
      window.print();
    }

    // ==========================================
    // 5. INTERACTIVE REALM MAP & STATION MODAL
    // ==========================================
    const stationData = {
      valhalla: {
        title: 'Valhalla Central (ヴァルハラ中央)',
        jp: 'アースガルズ主都 • Great Hall of Odin',
        icon: '🛡️',
        desc: 'The celestial fortress terminal constructed with five hundred and forty gates. Einherjar warriors depart daily for combat maneuvers. Connected directly to the Bifröst Prisma Bridge.',
        weather: 'Clear Auroral Sky • 14°C',
        deity: 'Odin Allfather & Valkyries (ᚩᛞᛁᚾ)'
      },
      himinbjorg: {
        title: 'Himinbjörg Bifröst Terminal (虹橋守護駅)',
        jp: 'ビフレスト起点 • Heimdall’s Watchtower',
        icon: '🌈',
        desc: 'Perched on the rainbow summit of Bifröst. Heimdall, who can hear grass grow on Midgard, checks all passports with his Gjallarhorn signaling beacon.',
        weather: 'Prismatic Refraction • 7-Hue Light',
        deity: 'Heimdall the White God (ᚺᛖᛁᛗᛞᚨᛚᛚ)'
      },
      convergence: {
        title: 'The Great Torii Convergence (天界大鳥居結界)',
        jp: '高天原・アースガルズ交差点 • Pan-Pantheon Gateway',
        icon: '⛩️',
        desc: 'The physical juncture where the World Tree Yggdrasil intertwines with the Heavenly Floating Bridge (Ame-no-Ukihashi). All pantheons honor perpetual peace within this jurisdiction.',
        weather: 'Astral Harmony • Zero Gravitational Wave',
        deity: 'All Kami & Æsir Concilium'
      },
      takamagahara: {
        title: 'Takamagahara High Spire (高天原中央駅)',
        jp: '日神天照大神主座 • Plain of High Heaven',
        icon: '☀️',
        desc: 'The supreme Shinto sun realm. Gleaming with cedar palaces, celestial rice paddies, and the Ama-no-Iwato solar sanctuary. The terminus of the Yamata-no-Orochi Super Shinkansen.',
        weather: 'Eternal Sunlight • 24°C Balmy',
        deity: 'Amaterasu-Ōmikami (天照大御神)'
      },
      izumo: {
        title: 'Izumo Grand Junction (出雲結びの大駅)',
        jp: '大国主神・縁結び交差点 • Land of Connections',
        icon: '🪢',
        desc: 'The gathering capital where eight million Kami convene every October (Kamiarizuki). Passengers arrive here to bind destined connections between mortal and divine threads.',
        weather: 'Sacred Mists • 19°C',
        deity: 'Ōkuninushi-no-Kami (大国主大神)'
      },
      nidavellir: {
        title: 'Niðavellir Deep Forge (鍛冶界駅)',
        jp: 'ドヴェルグの地底炉 • Dwarven Foundry Terminal',
        icon: '⚒️',
        desc: 'Deep subterranean steam-driven halls where Brokkr, Eitri, and Sons of Ivaldi forge hyper-rail components, Mjölnir spares, and locomotive hulls.',
        weather: 'Molten Geothermal • 32°C',
        deity: 'Brokkr & Eitri (Dwarven Smiths)'
      },
      midgard: {
        title: 'Midgard Port (ミズガルズ発着港)',
        jp: '人間界連絡港 • The Mortal Enclave',
        icon: '🌍',
        desc: 'The transit connection between divine stations and terrestrial pilgrim stations located in Kyoto and Oslo. Shielded by the World Serpent Jörmungandr’s ocean coils.',
        weather: 'Temperate Coastal • 16°C',
        deity: 'Thor (Protector of Humanity)'
      },
      fuji: {
        title: 'Mount Fuji Zenith Spire (富士山頂天狗駅)',
        jp: '木花之佐久夜毘売・霊峰山頂 • Sacred Stratosphere',
        icon: '🗻',
        desc: 'Station located on the sacred crater lip. Guarded by the elder Tengu winged heralds and the blossom goddess Konohanasakuya-hime.',
        weather: 'Pure Mountain Breeze • -2°C Snow Mist',
        deity: 'Konohanasakuya-hime & Sojobo'
      },
      fushimi: {
        title: 'Fushimi Fox Loop (伏見稲荷千本鳥居駅)',
        jp: '稲荷大社・朱色の万門環状線 • 10,000 Torii Loop',
        icon: '🦊',
        desc: 'An infinite vermilion corridor where trains thread through thousands of red gates. Station kiosks offer fried tofu inari sushi, celestial sake, and sacred talismans.',
        weather: 'Spring Blossom • 20°C',
        deity: 'Inari Okami & Myōbu Kitsune'
      },
      helheim: {
        title: 'Helheim Depths Terminus (ヘルヘイム冥界駅)',
        jp: '死者の国ヘルヘイム • The Frozen Underworld',
        icon: '💀',
        desc: 'Crosses the golden river Gjöll via the Gjallarbrú. The quiet resting domain for souls who did not perish in heroic sword battles. Silent sleeper pods only.',
        weather: 'Permafrost Chill • -18°C',
        deity: 'Goddess Hel (ᚺᛖᛚ)'
      },
      ryugu: {
        title: 'Ryūgū-jō Abyssal Water Station (竜宮海底駅)',
        jp: '海神綿津見宮 • Dragon Undersea Citadel',
        icon: '🌊',
        desc: 'Submerged deep within celestial oceanic trenches, constructed from red coral and mother-of-pearl. Trains travel through pressurized crystal glass water tunnels.',
        weather: 'Subaquatic Luminescence • 12°C',
        deity: 'Watatsumi Dragon God & Otohime'
      },
      yomi: {
        title: 'Yomi-no-Kuni Gate (黄泉比良坂終点駅)',
        jp: '伊邪那美命・黄泉の国 • The Nether Gateway',
        icon: '🌑',
        desc: 'The ancient stone boundary blocked by the Chibiki-no-Iwa boulder. Travelers to this station must not consume food cooked on the hearth of Yomi without a return sigil.',
        weather: 'Stygian Dusk • 8°C Cold Shadow',
        deity: 'Izanami-no-Mikoto (黄泉津大神)'
      },
      vanaheim: {
        title: 'Vanaheim Blooming Groves (豊穣界駅)',
        jp: 'ヴァン神族・美と豊穣の園 • Realm of the Vanir',
        icon: '🌸',
        desc: 'A garden paradise blessed by Freya and Njord. Gentle winds carry gold-dust pollen and gentle music. Dining car provisions are sourced from its orchards.',
        weather: 'Golden Spring • 22°C',
        deity: 'Freya & Njord (ᚠᚱᛖᛁᚨ)'
      }
    };

    function showStationModal(stationKey) {
      const data = stationData[stationKey];
      if (!data) return;

      document.getElementById('modal-station-icon').textContent = data.icon;
      document.getElementById('modal-station-title').textContent = data.title;
      document.getElementById('modal-station-jp').textContent = data.jp;
      document.getElementById('modal-station-desc').textContent = data.desc;
      document.getElementById('modal-station-weather').textContent = data.weather;
      document.getElementById('modal-station-deity').textContent = data.deity;

      const modalBookBtn = document.getElementById('modal-book-btn');
      modalBookBtn.onclick = () => {
        destSelect.value = stationKey === 'convergence' ? 'takamagahara' : stationKey;
        closeStationModal();
        document.getElementById('planner').scrollIntoView({
          behavior: 'smooth'
        });
        updateTicketPreview();
      };

      const modal = document.getElementById('station-modal');
      modal.classList.remove('hidden');
      setTimeout(() => modal.classList.remove('opacity-0'), 10);
    }

    function closeStationModal() {
      const modal = document.getElementById('station-modal');
      modal.classList.add('opacity-0');
      setTimeout(() => modal.classList.add('hidden'), 300);
    }

    // Modal backdrop click and Escape key dismissal
    document.getElementById('station-modal').addEventListener('click', (e) => {
      if (e.target.id === 'station-modal') {
        closeStationModal();
      }
    });
    window.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closeStationModal();
      }
    });

    // Map Line Filter Logic
    const filterBtns = document.querySelectorAll('#map-line-filters .filter-btn');
    filterBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        filterBtns.forEach(b => {
          b.classList.remove('active', 'bg-slate-800', 'text-white', 'border-divine-gold/40', 'shadow-md');
          b.classList.add('bg-void', 'text-slate-400');
        });
        btn.classList.add('active', 'bg-slate-800', 'text-white', 'border-divine-gold/40', 'shadow-md');
        btn.classList.remove('bg-void', 'text-slate-400');

        const filter = btn.getAttribute('data-filter');
        const bifrost = document.getElementById('line-bifrost');
        const thunder = document.getElementById('line-thunder');
        const underworld = document.getElementById('line-underworld');

        if (filter === 'all') {
          bifrost.style.opacity = '1';
          thunder.style.opacity = '1';
          underworld.style.opacity = '1';
        } else if (filter === 'bifrost') {
          bifrost.style.opacity = '1';
          thunder.style.opacity = '0.15';
          underworld.style.opacity = '0.15';
        } else if (filter === 'thunder') {
          bifrost.style.opacity = '0.15';
          thunder.style.opacity = '1';
          underworld.style.opacity = '0.15';
        } else if (filter === 'underworld') {
          bifrost.style.opacity = '0.15';
          thunder.style.opacity = '0.15';
          underworld.style.opacity = '1';
        }
      });
    });

    // ==========================================
    // 6. DEPARTURES REFRESH BUTTON
    // ==========================================
    document.getElementById('refresh-board-btn').addEventListener('click', () => {
      const btn = document.getElementById('refresh-board-btn');
      btn.innerHTML = '<span>⟳</span><span>Connecting Heimdall Radar...</span>';
      setTimeout(() => {
        btn.innerHTML = '<span>✓</span><span>Telemetry Synced!</span>';
        if (isSoundOn) playSanctuaryBell();
        setTimeout(() => {
          btn.innerHTML = '<span>⟳</span><span>Refresh Telemetry</span>';
        }, 1500);
      }, 700);
    });

    // Quick select train from fleet
    function selectTrainFleet(orig, dest) {
      originSelect.value = orig;
      destSelect.value = dest;
      updateTicketPreview();
      document.getElementById('planner').scrollIntoView({
        behavior: 'smooth'
      });
    }

    // ==========================================
    // 7. ORACLE CHAT ENGINE (Mímir & Omoikane)
    // ==========================================
    const oracleKnowledge = {
      baggage: {
        q: "Can I bring Mjölnir or Muramasa onboard?",
        a: "Under Section IV of the Asgard-Takamagahara Treaty: Divine armaments like Mjölnir, Gungnir, and Kusanagi-no-Tsurugi must be stored in the consecrated Spirit Lockers in Carriage 0. Valkyrie sidearms and katana under 3 shaku may remain sheathed at your hip under the Peace of Frigg."
      },
      dining: {
        q: "Mead of Poetry vs. Takama Ambrosia Bento?",
        a: "Both are served complimentary in First Class! The Mead of Poetry, brewed by Kvasir and Odin, grants poetic inspiration for 24 realm hours. The Takama Sun Bento contains sacred steamed red rice, matsutake mushrooms, and divine mochi blessed by Amaterasu."
      },
      yomi: {
        q: "Do I need a return talisman from Helheim / Yomi?",
        a: "CRITICAL REGULATION: Yes! When disembarking at either Helheim Depths or Yomi Gate, travelers must carry an active Imperial Magatama or Norse Vegvísir Talisman. Without it, you are forbidden by Izanami and Hel from re-boarding returning carriages."
      },
      heimdall: {
        q: "What if Heimdall sounds the Gjallarhorn during transit?",
        a: "In the unlikely event of Ragnarök emergency sirens, all train conductors (Thor, Susanoo, Amaterasu) will immediately engage the Bifröst emergency hyper-boost, transporting all mortal passengers safely to the high vault of Gimlé before planetary trembling occurs."
      }
    };

    function askOracle(key) {
      const data = oracleKnowledge[key];
      if (!data) return;
      appendMessage('user', data.q);
      setTimeout(() => {
        appendMessage('oracle', data.a);
      }, 400);
    }

    function handleOracleSubmit(e) {
      e.preventDefault();
      const input = document.getElementById('oracle-input');
      const text = input.value.trim();
      if (!text) return;
      appendMessage('user', text);
      input.value = '';

      setTimeout(() => {
        // Natural smart response from Mimir & Omoikane
        let reply = "By the wisdom of Mímir's well and the eight hundred myriad Kami, your path is favorable. Maintain your sacred seal upon your breast, and let the Bifröst bridge bear your spirit without dread.";
        const lower = text.toLowerCase();
        if (lower.includes('price') || lower.includes('cost') || lower.includes('fare')) {
          reply = "Standard mortal pilgrim fares range between 350 and 450 Astral Auric Coins. Consecrated Valkyries and Imperial Envoys travel under the treaty stipend.";
        } else if (lower.includes('speed') || lower.includes('fast') || lower.includes('time')) {
          reply = "Our hyper-velocity locomotives cruise at Mach 4.2 to Mach 6.2 along Bifröst light rails. Travel time between Asgard and Takamagahara is approximately 14 cosmic minutes.";
        } else if (lower.includes('food') || lower.includes('drink') || lower.includes('tea') || lower.includes('sake')) {
          reply = "The Kitsune Fire and Sleipnir lounges serve traditional Gyokuro green tea, warmed celestial sake, golden Asgard mead, and roasted sweet yams.";
        } else if (lower.includes('odin') || lower.includes('thor') || lower.includes('amaterasu')) {
          reply = "The conductor deities personally oversee each carriage's lightning dampers and warding barriers. You are in sovereign, divine hands.";
        }
        appendMessage('oracle', reply);
      }, 500);
    }

    function appendMessage(sender, text) {
      const box = document.getElementById('oracle-chat-box');
      const div = document.createElement('div');
      div.className = 'flex gap-3 ' + (sender === 'user' ? 'justify-end' : '');

      if (sender === 'user') {
        div.innerHTML = `
          <div class="bg-vermilion/20 border border-vermilion/40 p-3 rounded-2xl rounded-tr-none max-w-xl text-slate-100 text-xs sm:text-sm">
            <div class="font-bold text-[10px] text-amber-300 mb-0.5">You (Pilgrim)</div>
            <p>${text}</p>
          </div>
          <div class="w-8 h-8 rounded-full bg-vermilion border border-white/20 flex items-center justify-center text-xs shrink-0 font-bold">
            客
          </div>
        `;
      } else {
        div.innerHTML = `
          <div class="w-8 h-8 rounded-full bg-cyan-900 border border-cyan-400 flex items-center justify-center text-base shrink-0">
            ᛗ
          </div>
          <div class="bg-slate-900 p-3 rounded-2xl rounded-tl-none border border-slate-800 max-w-xl text-xs sm:text-sm">
            <div class="font-cinzel font-bold text-xs text-cyan-300 mb-1">Mímir & Omoikane</div>
            <p class="text-slate-300 leading-relaxed">${text}</p>
          </div>
        `;
      }
      box.appendChild(div);
      box.scrollTop = box.scrollHeight;
    }

    // ==========================================
    // 8. MOBILE MENU TOGGLE
    // ==========================================
    const mobileBtn = document.getElementById('mobile-menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');
    const menuOpen = document.getElementById('menu-icon-open');
    const menuClose = document.getElementById('menu-icon-close');

    function toggleMobileMenu() {
      mobileMenu.classList.toggle('hidden');
      menuOpen.classList.toggle('hidden');
      menuClose.classList.toggle('hidden');
    }

    mobileBtn.addEventListener('click', toggleMobileMenu);

    document.querySelectorAll('.mobile-nav-link').forEach(link => {
      link.addEventListener('click', () => {
        toggleMobileMenu();
      });
    });

    // Initialize initial state
    updateTicketPreview();
  </script>
</body>

</html>