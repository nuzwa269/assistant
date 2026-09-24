import { Link } from "react-router-dom";
import { useEffect, type ElementType } from "react";
import { useLocation } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Header } from "@/components/layout/Header";
import { prebuiltAssistants, customAssistants } from "@/data/mock-data";
import {
  ArrowRight,
  BookOpen,
  Bot,
  BrainCircuit,
  Bug,
  Building2,
  Check,
  ClipboardList,
  Code,
  Coins,
  Cpu,
  FileText,
  FolderOpen,
  Globe,
  GraduationCap,
  Image,
  Layers,
  LayoutDashboard,
  Lightbulb,
  Lock,
  MessageSquare,
  Palette,
  PenTool,
  PlayCircle,
  Rocket,
  ShieldCheck,
  Smartphone,
  Sparkles,
  Star,
  TrendingUp,
  Users,
  Wand2,
  Zap,
} from "lucide-react";

const iconMap: Record<string, ElementType> = {
  GraduationCap,
  Building2,
  Bug,
  Lightbulb,
  Code,
  Rocket,
  TrendingUp,
  Layers,
  Globe,
  LayoutDashboard,
  Smartphone,
};

const heroStats = [
  { value: "24/7", label: "AI coaching" },
  { value: "20", label: "free starter credits" },
  { value: "0", label: "API keys needed" },
];

const features = [
  {
    icon: Sparkles,
    title: "AI-powered learning",
    desc: "Learn any technical topic with personalized AI tutors that adapt explanations to your pace.",
  },
  {
    icon: FolderOpen,
    title: "Project workspaces",
    desc: "Organize learning and building into projects with dedicated conversations and assistants.",
  },
  {
    icon: Users,
    title: "Custom assistants",
    desc: "Create focused expert personas with your own prompts for repeatable coaching workflows.",
  },
  {
    icon: Star,
    title: "Save and review",
    desc: "Bookmark high-value AI responses so insights, code snippets, and plans are easy to revisit.",
  },
  {
    icon: Coins,
    title: "Flexible credits",
    desc: "Start free, top up credits, or upgrade plans when your coaching and creation needs grow.",
  },
  {
    icon: ShieldCheck,
    title: "Protected dashboard",
    desc: "Authentication, private conversations, and role-protected admin surfaces stay safely separated.",
  },
];

const educationSuite = [
  {
    icon: Cpu,
    title: "EduAssess Pro",
    use: "Use it when you need quizzes, checks for understanding, or assessment ideas.",
    outcome: "Create and manage assessments faster with AI-supported structure.",
  },
  {
    icon: PenTool,
    title: "StudyByte Pro",
    use: "Use it for quick revision, micro-lessons, and bite-sized study content.",
    outcome: "Turn big ideas into focused learning bytes students can absorb quickly.",
  },
  {
    icon: GraduationCap,
    title: "EduPlanner Pro",
    use: "Use it when planning lessons, units, or classroom learning paths.",
    outcome: "Move from blank page to practical lesson plans with less friction.",
  },
  {
    icon: ClipboardList,
    title: "EduPaper Pro",
    use: "Use it to draft structured papers, worksheets, and instructional documents.",
    outcome: "Produce organized education materials with clearer formatting and intent.",
  },
  {
    icon: Palette,
    title: "EduActivity Pro",
    use: "Use it when lessons need interactive puzzles, activities, or creative engagement.",
    outcome: "Make learning more active, memorable, and classroom-ready.",
  },
  {
    icon: FileText,
    title: "24x7 Tutor Pro",
    use: "Use it for always-available doubt solving, concept review, and guided practice.",
    outcome: "Give learners a responsive tutoring companion whenever questions appear.",
  },
];

const capabilities = [
  {
    icon: Image,
    title: "Prompt & Image Generation Tools",
    description: "Turn text, style directions, and simple ideas into polished visual prompts and image concepts.",
    use: "Use when you need creative assets, prompt ideas, or visual direction.",
    outcome: "Move from vague idea to production-ready creative brief faster.",
  },
  {
    icon: MessageSquare,
    title: "Conversation Starters",
    description: "Starter prompts appear as quick chips when an assistant has recommended first questions.",
    use: "Use when you want momentum but do not know exactly what to ask first.",
    outcome: "Begin useful conversations in one click and learn good prompt patterns by example.",
  },
  {
    icon: BrainCircuit,
    title: "Multi-model AI Gateway",
    description: "Admins can connect assistants to modern Google Gemini and OpenAI GPT-family models.",
    use: "Use fast models for simple replies and stronger reasoning models for harder work.",
    outcome: "Match cost, speed, and intelligence to each assistant workflow.",
  },
  {
    icon: Lock,
    title: "Private Saved Responses",
    description: "Important assistant answers can be saved to your account for later review.",
    use: "Use when the AI gives reusable explanations, architecture ideas, code guidance, or study notes.",
    outcome: "Build a personal knowledge base from your best coaching sessions.",
  },
];

const assistantUseOutcome: Record<string, { use: string; outcome: string }> = {
  "AI MASTER": {
    use: "Use it when you want to learn coding, AI, SaaS, or WordPress from beginner to professional level.",
    outcome: "Clear understanding of complex tech concepts explained at your current level.",
  },
  "IDEA MASTER": {
    use: "Use it when you need realistic startup or SaaS ideas that match your skills and budget.",
    outcome: "Validated, buildable business ideas with clear next steps and market logic.",
  },
  "STARTUP ROCKET": {
    use: "Use it when you have an idea and need a step-by-step path to validate, build an MVP, and launch.",
    outcome: "A practical launch roadmap with real-world payment and pricing guidance.",
  },
  "MARKETING MASTER": {
    use: "Use it when you need SEO, content strategy, paid ads, or growth tactics for any budget.",
    outcome: "Data-driven marketing plans from zero-budget tactics to paid campaigns.",
  },
  "BLUEPRINT ARCHITECT": {
    use: "Use it when you have a complex enterprise idea (POS, ERP, School Management) and need a complete technical blueprint.",
    outcome: "A complete Master Prompt ready to paste into any AI coding tool.",
  },
  "CODESATHI": {
    use: "Use it when building websites, web apps, SaaS products, or browser extensions hands-on.",
    outcome: "Production-ready code with security best practices and clean architecture baked in.",
  },
  "WORDPRESS BUSINESS ARCHITECT": {
    use: "Use it when you need custom business software as a WordPress plugin (Gym, Clinic, Tailor Shop, etc.).",
    outcome: "Production-ready WordPress plugins using pure native PHP and jQuery.",
  },
  "DASHBOARD ARCHITECT": {
    use: "Use it when you need a high-performance admin dashboard with charts, logs, and CRUD systems.",
    outcome: "A complete admin dashboard built with Next.js and Supabase.",
  },
  "MOBILE ARCHITECT PRO": {
    use: "Use it when you need a mobile app for Web, Android, and iOS using Flutter or native Kotlin.",
    outcome: "Real working Flutter or native Android code from a single shared codebase.",
  },
  "BUG FIXER PRO": {
    use: "Use it when you have bugs or errors across any stack and need root cause analysis.",
    outcome: "Fixed bugs with root causes identified, never just symptoms hidden.",
  },
};

const assistantCards = [
  ...prebuiltAssistants.map((assistant) => ({
    name: assistant.name,
    description: assistant.description,
    use: assistantUseOutcome[assistant.name]?.use ?? "Use it when you need expert help in this area.",
    outcome: assistantUseOutcome[assistant.name]?.outcome ?? "Practical, actionable results from your coaching session.",
    icon: iconMap[assistant.icon] ?? Bot,
    category: assistant.category,
  })),
  ...customAssistants.map((assistant) => ({
    name: assistant.name,
    description: assistant.description,
    use: "Use custom assistants for repeatable workflows that need your preferred rules, tone, and expertise.",
    outcome: "A reusable specialist that matches your projects instead of a generic chat experience.",
    icon: iconMap[assistant.icon] ?? Wand2,
    category: assistant.category,
  })),
];

const steps = [
  {
    icon: Rocket,
    title: "Create your free account",
    body: "Start from the landing page and choose Sign Up Free. Register with Google Sign-In or email and password. New users begin with 20 free credits and no card requirement.",
  },
  {
    icon: Bot,
    title: "Open Assistants",
    body: "Browse prebuilt coaches for coding, architecture, debugging, explanations, education workflows, and custom specialist use cases.",
  },
  {
    icon: PlayCircle,
    title: "Activate and chat",
    body: "Activate an assistant, open the dashboard, select your active assistant above the chat box, type your request, and send your message.",
  },
  {
    icon: Wand2,
    title: "Use tools and starters",
    body: "Click conversation starter chips for quick prompts, create custom assistants when you need a repeatable expert, and save important responses for later.",
  },
  {
    icon: Layers,
    title: "Work inside projects",
    body: "Group learning, building, and coaching conversations into projects so context stays organized as your work grows.",
  },
  {
    icon: Zap,
    title: "Apply best practices",
    body: "Ask for the outcome you want, include context and constraints, request step-by-step reasoning, switch assistants for specialist tasks, and top up credits before long sessions.",
  },
];

const benefits = [
  "Public landing page first; protected dashboard only after authentication.",
  "One place to learn, design, debug, write, teach, and build with AI guidance.",
  "No external API-key setup for users — the AI engine is built into the platform.",
  "Credits, plans, saved responses, projects, custom assistants, and admin controls remain intact.",
];

export default function Index() {
  const location = useLocation();

  useEffect(() => {
    if (!location.hash) return;

    const sectionId = location.hash.replace("#", "");
    const scrollToSection = window.setTimeout(() => {
      document.getElementById(sectionId)?.scrollIntoView({
        behavior: "smooth",
        block: "start",
      });
    }, 0);

    return () => window.clearTimeout(scrollToSection);
  }, [location.hash]);

  return (
    <div className="min-h-screen bg-background text-foreground">
      <Header />

      <main>
        <section className="landing-hero relative overflow-hidden border-b border-border/60 px-4 py-20 sm:px-6 lg:px-8 lg:py-28">
          <div className="pointer-events-none absolute left-1/2 top-24 h-72 w-72 -translate-x-1/2 rounded-full bg-primary/20 blur-3xl" />
          <div className="container relative z-10 grid items-center gap-14 lg:grid-cols-[1.05fr_0.95fr]">
            <div className="animate-fade-in space-y-8">
              <Badge className="rounded-full border border-primary/25 bg-primary/10 px-4 py-1.5 text-primary shadow-sm hover:bg-primary/10">
                <Sparkles className="mr-2 h-3.5 w-3.5" /> AI Assistant Platform
              </Badge>
              <div className="space-y-5">
                <h1 className="font-heading text-5xl font-extrabold tracking-tight text-foreground sm:text-6xl lg:text-7xl">
                  CoachPro AI
                </h1>
                <h2 className="max-w-3xl font-heading text-3xl font-bold leading-tight text-foreground sm:text-4xl lg:text-5xl">
                  A premium AI workspace for learning, building, teaching, and creating faster.
                </h2>
                <p className="max-w-2xl text-lg text-muted-foreground sm:text-xl">
                  Meet a suite of specialist AI assistants that help you understand code, design systems, fix bugs, plan lessons, generate ideas, save insights, and move from questions to outcomes.
                </p>
              </div>
              <div className="flex flex-col gap-3 sm:flex-row">
                <Button asChild size="lg" className="h-13 rounded-full px-7 text-base font-semibold shadow-xl shadow-primary/20 transition-transform hover:-translate-y-0.5">
                  <a href="#ai-assistants">
                    Explore AI Assistants <ArrowRight className="ml-2 h-4 w-4" />
                  </a>
                </Button>
                <Button asChild size="lg" variant="outline" className="h-13 rounded-full border-primary/30 bg-background/70 px-7 text-base font-semibold backdrop-blur transition-transform hover:-translate-y-0.5 hover:bg-primary/5">
                  <Link to="/signup">Sign Up Free</Link>
                </Button>
              </div>
              <div className="grid gap-3 pt-2 sm:grid-cols-3">
                {heroStats.map((stat) => (
                  <div key={stat.label} className="rounded-2xl border border-border/70 bg-card/75 p-4 shadow-sm backdrop-blur">
                    <p className="font-heading text-2xl font-bold text-foreground">{stat.value}</p>
                    <p className="text-sm text-muted-foreground">{stat.label}</p>
                  </div>
                ))}
              </div>
            </div>

            <div className="relative mx-auto w-full max-w-xl animate-float-slow">
              <div className="absolute -inset-6 rounded-[2rem] bg-secondary/15 blur-2xl" />
              <div className="relative overflow-hidden rounded-[2rem] border border-white/40 bg-card/85 p-5 shadow-2xl shadow-secondary/10 backdrop-blur-xl">
                <div className="mb-5 flex items-center justify-between rounded-2xl bg-muted/60 p-3">
                  <div>
                    <p className="text-sm font-semibold">Assistant Command Center</p>
                    <p className="text-xs text-muted-foreground">Pick a coach. Ask. Save. Build.</p>
                  </div>
                  <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-lg">
                    <Bot className="h-5 w-5" />
                  </div>
                </div>
                <div className="space-y-3">
                  {prebuiltAssistants.map((assistant, index) => {
                    const Icon = iconMap[assistant.icon] ?? Bot;
                    return (
                      <div
                        key={assistant.id}
                        className="group flex items-center gap-3 rounded-2xl border border-border/70 bg-background/80 p-3 transition-all hover:-translate-y-1 hover:border-primary/40 hover:shadow-lg"
                        style={{ animationDelay: `${index * 90}ms` }}
                      >
                        <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                          <Icon className="h-5 w-5" />
                        </div>
                        <div className="min-w-0 flex-1">
                          <p className="font-semibold text-foreground">{assistant.name}</p>
                          <p className="line-clamp-1 text-sm text-muted-foreground">{assistant.description}</p>
                        </div>
                        <Badge variant="outline" className="hidden rounded-full sm:inline-flex">Ready</Badge>
                      </div>
                    );
                  })}
                </div>
              </div>
            </div>
          </div>
        </section>

        <section id="features" className="section-warm py-20">
          <div className="container">
            <div className="mx-auto mb-12 max-w-3xl text-center">
              <Badge variant="outline" className="rounded-full">Features</Badge>
              <h2 className="mt-4 font-heading text-3xl font-bold sm:text-4xl">Everything users need before the dashboard</h2>
              <p className="mt-3 text-muted-foreground">CoachPro AI combines guided learning, project organization, assistant customization, credits, and saved knowledge in a polished SaaS experience.</p>
            </div>
            <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
              {features.map((feature) => {
                const Icon = feature.icon;
                return (
                  <Card key={feature.title} className="group overflow-hidden border-border/70 bg-card/90 transition-all duration-300 hover:-translate-y-2 hover:border-primary/40 hover:shadow-xl">
                    <CardContent className="p-6">
                      <div className="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-secondary/10 text-secondary transition-all group-hover:scale-110 group-hover:bg-secondary group-hover:text-secondary-foreground">
                        <Icon className="h-6 w-6" />
                      </div>
                      <h3 className="font-heading text-xl font-semibold">{feature.title}</h3>
                      <p className="mt-2 text-sm text-muted-foreground">{feature.desc}</p>
                    </CardContent>
                  </Card>
                );
              })}
            </div>
          </div>
        </section>

        <section id="ai-assistants" className="relative overflow-hidden py-20">
          <div className="absolute inset-x-0 top-0 h-40 bg-primary/5" />
          <div className="container relative">
            <div className="mb-12 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
              <div className="max-w-3xl">
                <Badge className="rounded-full bg-secondary/10 text-secondary hover:bg-secondary/10">AI Assistants Overview</Badge>
                <h2 className="mt-4 font-heading text-3xl font-bold sm:text-4xl">10 specialist AI assistants for learning, building, and growing</h2>
                <p className="mt-3 text-muted-foreground">Each card explains what the assistant does, when to use it, and the practical outcome users can expect after signing in.</p>
              </div>
              <Button asChild variant="outline" className="rounded-full">
                <Link to="/signup">Activate assistants <ArrowRight className="ml-2 h-4 w-4" /></Link>
              </Button>
            </div>
            <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
              {assistantCards.map((assistant) => {
                const Icon = assistant.icon;
                return (
                  <Card key={`${assistant.category}-${assistant.name}`} className="group relative overflow-hidden border-border/70 bg-card transition-all duration-300 hover:-translate-y-2 hover:border-primary/50 hover:shadow-2xl hover:shadow-primary/10">
                    <div className="absolute right-0 top-0 h-24 w-24 translate-x-8 -translate-y-8 rounded-full bg-primary/10 blur-2xl transition-all group-hover:bg-secondary/20" />
                    <CardContent className="relative p-6">
                      <div className="mb-5 flex items-start justify-between gap-4">
                        <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary transition-all group-hover:rotate-3 group-hover:scale-110 group-hover:bg-primary group-hover:text-primary-foreground">
                          <Icon className="h-6 w-6" />
                        </div>
                        <Badge variant="outline" className="rounded-full text-xs">{assistant.category}</Badge>
                      </div>
                      <h3 className="font-heading text-xl font-bold">{assistant.name}</h3>
                      <p className="mt-2 text-sm text-muted-foreground">{assistant.description}</p>
                      <div className="mt-5 space-y-3 text-sm">
                        <div className="rounded-2xl bg-muted/70 p-3">
                          <p className="font-semibold text-foreground">When to use it</p>
                          <p className="mt-1 text-muted-foreground">{assistant.use}</p>
                        </div>
                        <div className="rounded-2xl bg-primary/10 p-3">
                          <p className="font-semibold text-foreground">Expected outcome</p>
                          <p className="mt-1 text-muted-foreground">{assistant.outcome}</p>
                        </div>
                      </div>
                    </CardContent>
                  </Card>
                );
              })}
            </div>
          </div>
        </section>

        <section id="how-it-works" className="bg-[#FAFAFA] py-20 text-[#111827]">
          <div className="container">
            <div className="mx-auto mb-12 max-w-3xl text-center">
              <Badge className="rounded-full border border-[#9333EA]/25 bg-[#9333EA]/10 text-[#9333EA] hover:bg-[#9333EA]/10">How It Works</Badge>
              <h2 className="mt-4 font-heading text-3xl font-bold text-[#111827] sm:text-4xl">From first visit to first AI outcome</h2>
              <p className="mt-3 text-[#4B5563]">A guided onboarding flow turns project instructions and in-app help into a clear path for new users.</p>
            </div>
            <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
              {steps.map((step, index) => {
                const Icon = step.icon;
                return (
                  <div key={step.title} className="group rounded-[1.75rem] border border-border bg-white p-6 text-[#111827] shadow-xl transition-all hover:-translate-y-2 hover:border-[#FF8A00]/35 hover:shadow-2xl">
                    <div className="mb-5 flex items-center justify-between">
                      <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#9333EA]/10 text-[#9333EA] shadow-lg">
                        <Icon className="h-6 w-6" />
                      </div>
                      <span className="font-heading text-4xl font-black text-[#9333EA]/20">0{index + 1}</span>
                    </div>
                    <h3 className="font-heading text-xl font-semibold text-[#111827]">{step.title}</h3>
                    <p className="mt-2 text-sm text-[#4B5563]">{step.body}</p>
                  </div>
                );
              })}
            </div>
          </div>
        </section>

        <section id="benefits" className="py-20">
          <div className="container grid gap-10 lg:grid-cols-[0.9fr_1.1fr] lg:items-center">
            <div>
              <Badge variant="outline" className="rounded-full">Platform Benefits</Badge>
              <h2 className="mt-4 font-heading text-3xl font-bold sm:text-4xl">Modern onboarding without weakening security</h2>
              <p className="mt-4 text-muted-foreground">The landing page is public and persuasive, while the actual product remains behind the existing protected route model. Users learn the value first, then authenticate, then continue directly to the dashboard.</p>
              <div className="mt-7 space-y-3">
                {benefits.map((benefit) => (
                  <div key={benefit} className="flex gap-3 rounded-2xl border border-border/70 bg-card p-4 shadow-sm">
                    <Check className="mt-0.5 h-5 w-5 shrink-0 text-primary" />
                    <p className="text-sm text-muted-foreground">{benefit}</p>
                  </div>
                ))}
              </div>
            </div>
            <div className="relative">
              <div className="absolute -inset-4 rounded-[2rem] bg-gradient-to-br from-primary/20 to-secondary/20 blur-2xl" />
              <div className="relative grid gap-4 rounded-[2rem] border border-border bg-card p-5 shadow-2xl sm:grid-cols-2">
                {[
                  { icon: BookOpen, label: "Learn", text: "Explain concepts and practice skills." },
                  { icon: Code, label: "Build", text: "Plan components, APIs, and systems." },
                  { icon: Bug, label: "Fix", text: "Diagnose issues step by step." },
                  { icon: Palette, label: "Create", text: "Generate prompts, activities, and content." },
                ].map((item) => {
                  const Icon = item.icon;
                  return (
                    <div key={item.label} className="rounded-2xl bg-muted/60 p-5 transition-transform hover:-translate-y-1">
                      <Icon className="h-7 w-7 text-primary" />
                      <p className="mt-4 font-heading text-lg font-semibold">{item.label}</p>
                      <p className="mt-1 text-sm text-muted-foreground">{item.text}</p>
                    </div>
                  );
                })}
              </div>
            </div>
          </div>
        </section>

        <section className="px-4 pb-20 sm:px-6 lg:px-8">
          <div className="container overflow-hidden rounded-[2rem] bg-brand-dark p-8 text-center text-white shadow-2xl md:p-12">
            <div className="mx-auto max-w-3xl">
              <Sparkles className="mx-auto h-10 w-10 text-white" />
              <h2 className="mt-5 font-heading text-3xl font-bold sm:text-4xl">Ready to meet your AI coaching team?</h2>
              <p className="mt-3 text-white/82">Sign up free, use Google or email registration, and continue directly into the protected dashboard to activate assistants and start your first conversation.</p>
              <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                <Button asChild size="lg" className="rounded-full bg-white px-8 font-semibold text-brand-dark hover:bg-white/90">
                  <Link to="/signup">Sign Up Free <ArrowRight className="ml-2 h-4 w-4" /></Link>
                </Button>
                <Button asChild size="lg" variant="outline" className="rounded-full border-white/40 bg-white/10 px-8 font-semibold text-white hover:bg-white/20 hover:text-white">
                  <a href="#how-it-works">See how it works</a>
                </Button>
              </div>
            </div>
          </div>
        </section>
      </main>

      {/* Minimal copyright bar — landing page does not need the full app footer */}
      <div className="bg-muted py-4 text-center text-xs text-muted-foreground sm:text-sm">
        &copy; {new Date().getFullYear()} CoachPro AI. All Rights Reserved.
      </div>
    </div>
  );
}
