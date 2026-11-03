import "server-only";

// The admin is English for now. Amharic and Tigrinya map to the English
// strings until the Mini App needs its own dictionaries.
const en = () => import("./dictionaries/en.json").then((module) => module.default);

const dictionaries = { en, am: en, ti: en };

export const getDictionary = async (locale: string) => {
  const load = dictionaries[locale as keyof typeof dictionaries] ?? en;
  return load();
};
