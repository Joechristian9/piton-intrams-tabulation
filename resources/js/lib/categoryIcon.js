import {
    Award,
    Brain,
    Camera,
    Crown,
    Drama,
    Dumbbell,
    Flag,
    Flame,
    Flower2,
    Footprints,
    Gem,
    GraduationCap,
    Heart,
    ListChecks,
    MessageSquareText,
    Mic,
    Music,
    Palette,
    ScanFace,
    Shirt,
    Sparkles,
    Star,
    Sun,
    Target,
    Waves,
    Zap,
} from "lucide-react";

// Icons an admin can pick for a category. The keys must match categoryIcons.json,
// which the server validates against (tests/js/categoryIcon.test.mjs checks they agree).
export const CATEGORY_ICONS = {
    music: { icon: Music, label: "Music" },
    dumbbell: { icon: Dumbbell, label: "Sports" },
    waves: { icon: Waves, label: "Swim" },
    gem: { icon: Gem, label: "Gem" },
    shirt: { icon: Shirt, label: "Shirt" },
    mic: { icon: Mic, label: "Microphone" },
    star: { icon: Star, label: "Star" },
    face: { icon: ScanFace, label: "Face" },
    speech: { icon: MessageSquareText, label: "Speech" },
    sparkles: { icon: Sparkles, label: "Sparkles" },
    drama: { icon: Drama, label: "Costume" },
    camera: { icon: Camera, label: "Camera" },
    graduation: { icon: GraduationCap, label: "Uniform" },
    brain: { icon: Brain, label: "Intelligence" },
    footprints: { icon: Footprints, label: "Runway" },
    palette: { icon: Palette, label: "Creative" },
    crown: { icon: Crown, label: "Crown" },
    award: { icon: Award, label: "Award" },
    flag: { icon: Flag, label: "Flag" },
    zap: { icon: Zap, label: "Energy" },
    heart: { icon: Heart, label: "Heart" },
    target: { icon: Target, label: "Target" },
    flame: { icon: Flame, label: "Flame" },
    flower: { icon: Flower2, label: "Flower" },
    sun: { icon: Sun, label: "Sun" },
    checklist: { icon: ListChecks, label: "Checklist" },
};

// Keyword → icon key, checked in order, so "Swim Wear" matches swim before the generic "wear".
const BY_KEYWORD = [
    [/swim|bikini/, "waves"],
    [/sport|athlet|gym/, "dumbbell"],
    [/interview|q\s*&\s*a|question/, "mic"],
    [/production|dance|opening/, "music"],
    [/talent|perform/, "star"],
    [/face|beauty/, "face"],
    [/delivery|speech|speak|communicat/, "speech"],
    [/appeal|x-?factor|charisma|over-?all/, "sparkles"],
    [/formal|gown|evening/, "gem"],
    [/costume|cultural|national|festival/, "drama"],
    [/photo/, "camera"],
    [/uniform|school|academic/, "graduation"],
    [/intelligen|wit|brain/, "brain"],
    [/poise|walk|runway|ramp/, "footprints"],
    [/creativ|art|design/, "palette"],
    [/casual|wear|attire|outfit/, "shirt"],
];

// Names with no keyword get distinct icons by their position in the menu.
const FALLBACK = ["star", "flag", "award", "crown", "zap", "heart", "target", "flame"];

/** The icon key used when the admin left the icon on Auto: from the name, else by index. */
export function autoIconKey(name, index = 0) {
    const lower = (name ?? "").toLowerCase();
    const hit = BY_KEYWORD.find(([pattern]) => pattern.test(lower));
    return hit ? hit[1] : FALLBACK[index % FALLBACK.length];
}

/** A category's icon component: the picked one (`key`), else the automatic one. */
export default function categoryIcon(name, index = 0, key = null) {
    return (CATEGORY_ICONS[key] ?? CATEGORY_ICONS[autoIconKey(name, index)]).icon;
}
