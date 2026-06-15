---
name: Iron & Oak Foundation
colors:
  surface: '#f7f9fb'
  surface-dim: '#d8dadc'
  surface-bright: '#f7f9fb'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f2f4f6'
  surface-container: '#eceef0'
  surface-container-high: '#e6e8ea'
  surface-container-highest: '#e0e3e5'
  on-surface: '#191c1e'
  on-surface-variant: '#45474c'
  inverse-surface: '#2d3133'
  inverse-on-surface: '#eff1f3'
  outline: '#75777d'
  outline-variant: '#c5c6cd'
  surface-tint: '#545f73'
  primary: '#091426'
  on-primary: '#ffffff'
  primary-container: '#1e293b'
  on-primary-container: '#8590a6'
  inverse-primary: '#bcc7de'
  secondary: '#914d00'
  on-secondary: '#ffffff'
  secondary-container: '#fc9430'
  on-secondary-container: '#663500'
  tertiary: '#041528'
  on-tertiary: '#ffffff'
  tertiary-container: '#1a2a3e'
  on-tertiary-container: '#8191a9'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#d8e3fb'
  primary-fixed-dim: '#bcc7de'
  on-primary-fixed: '#111c2d'
  on-primary-fixed-variant: '#3c475a'
  secondary-fixed: '#ffdcc3'
  secondary-fixed-dim: '#ffb77d'
  on-secondary-fixed: '#2f1500'
  on-secondary-fixed-variant: '#6e3900'
  tertiary-fixed: '#d3e4fe'
  tertiary-fixed-dim: '#b7c8e1'
  on-tertiary-fixed: '#0b1c30'
  on-tertiary-fixed-variant: '#38485d'
  background: '#f7f9fb'
  on-background: '#191c1e'
  surface-variant: '#e0e3e5'
typography:
  display:
    fontFamily: Montserrat
    fontSize: 64px
    fontWeight: '800'
    lineHeight: 72px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Montserrat
    fontSize: 40px
    fontWeight: '700'
    lineHeight: 48px
    letterSpacing: -0.01em
  headline-lg-mobile:
    fontFamily: Montserrat
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
  headline-md:
    fontFamily: Montserrat
    fontSize: 24px
    fontWeight: '700'
    lineHeight: 32px
  body-lg:
    fontFamily: Work Sans
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Work Sans
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  label-caps:
    fontFamily: Work Sans
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 20px
    letterSpacing: 0.1em
  button:
    fontFamily: Montserrat
    fontSize: 16px
    fontWeight: '600'
    lineHeight: 16px
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  unit: 8px
  gutter: 24px
  margin-mobile: 16px
  margin-desktop: 80px
  section-padding: 120px
---

## Brand & Style

The design system is engineered to project **reliability, structural integrity, and modern craftsmanship**. It targets property owners and developers who value precision and professionalism over budget-cutting. 

The visual style is **Corporate Modern with a "Industrial Refined" edge**. It utilizes high-contrast transitions between deep slate backgrounds and crisp white content areas to mimic the blueprint-to-build process. The aesthetic avoids unnecessary flourishes, focusing instead on heavy-weight typography and a strict adherence to a grid, evoking the feeling of a well-organized job site. 

The emotional response should be one of "Absolute Competence"—the user should feel that the company is physically robust and digitally sophisticated.

## Colors

The palette is anchored by **Deep Slate Gray** (#1E293B), representing the strength of steel and stone. This is the primary color for headers, footers, and high-impact text. 

**Construction Orange** (#F28C28) is the high-visibility accent. It is used sparingly but strategically for primary calls-to-action, status indicators, and critical navigational elements to ensure they stand out against the industrial neutrals.

**Slate Blue-Gray** (#64748B) serves as a secondary supporting color for icons and borders, bridging the gap between the dark primary and the **Cool White** (#F8FAFC) background. The background should remain mostly white to ensure readability, using the deep slate for full-bleed section breaks to create a rhythmic, high-contrast scrolling experience.

## Typography

This design system uses a pairing of **Montserrat** for headlines and **Work Sans** for body text. 

**Montserrat** provides a geometric, bold architectural feel. Large display titles should use the ExtraBold (800) weight with tight tracking to mimic the density of heavy machinery or structural beams. 

**Work Sans** was selected for its exceptional legibility and professional, neutral tone. It handles technical specifications and long-form service descriptions with clarity. 

All "Label" styles should be set in uppercase with increased letter spacing to serve as category headers or breadcrumbs, providing a functional, utility-first hierarchy.

## Layout & Spacing

The layout follows a **12-column fixed grid** for desktop (max-width: 1280px) and a **4-column fluid grid** for mobile. 

We utilize a **heavy vertical rhythm**. Section transitions are dramatic, using 120px of padding to allow high-quality construction photography room to breathe. The spacing is based on an 8px base unit, ensuring all elements align to a predictable "construction" grid.

- **Desktop (1024px+):** 12 columns, 80px side margins. Content is often split 50/50 between text blocks and full-bleed imagery.
- **Tablet (768px - 1023px):** 8 columns, 40px side margins. 
- **Mobile (Up to 767px):** 4 columns, 16px side margins. Stack all side-by-side elements vertically, prioritizing the lead generation form at the top or bottom of the scroll.

## Elevation & Depth

To maintain the "robust" brand promise, this design system avoids soft, floating neomorphism. Instead, it uses **Tonal Layering and Hard Borders**.

- **Level 0 (Base):** Clean White or Light Gray (#F1F5F9).
- **Level 1 (Cards/Forms):** White background with a 1px solid border in Slate (#E2E8F0). Do not use shadows for standard cards; use a 4px offset "hard" shadow in Slate (#1E293B) only on hover to simulate a physical push-button.
- **Depth via Imagery:** Use semi-transparent Slate overlays (60% opacity) on top of large hero images to ensure white text remains legible while keeping the "industrial" texture of the site photo visible.

## Shapes

The shape language is **Soft (0.25rem/4px)**. 

While the brand is robust, completely sharp corners can feel dated or aggressive. A subtle 4px radius on buttons, input fields, and images provides a modern, "finished" look—like a polished piece of stone or a sanded beam. 

**Portfolio images** should maintain this 4px radius. **Primary CTAs** may occasionally use a 0px radius for a more "brutalist" and urgent impact in the Hero section, but the 4px standard applies to all functional UI components.

## Components

- **Buttons:** 
    - *Primary:* Construction Orange background, white text, bold Montserrat. No border. On hover, darken the orange by 10%.
    - *Secondary:* Deep Slate background, white text.
    - *Outline:* 2px solid Deep Slate border, transparent background, Deep Slate text.
- **Input Fields:** 1px solid Slate-300 borders. Focus state should use a 2px Construction Orange bottom-border only, mimicking a measuring tape or level line.
- **Project Portfolio Grid:** A masonry or strict grid with "Quick View" overlays. The overlay should be the Deep Slate primary color at 90% opacity with white Montserrat typography.
- **Service Chips:** Small, rectangular tags with light gray backgrounds and Slate-600 text to categorize project types (e.g., "Residential," "Commercial").
- **Testimonial Cards:** Simple, bordered boxes (Level 1 elevation) with a quote icon in Construction Orange.
- **Lead Gen Form:** A high-contrast block, often utilizing a Deep Slate background with white input labels to distinguish it from the rest of the page content.