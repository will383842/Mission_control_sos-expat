// ========================================
// src/data/countries.ts - 195 PAYS COMPLETS - 10 LANGUES
// ========================================

// Interface pour un pays
export interface CountryData {
  code: string;
  nameFr: string;   // Français
  nameEn: string;   // English
  nameEs: string;   // Español
  nameDe: string;   // Deutsch
  namePt: string;   // Português
  nameZh: string;   // 中文
  nameAr: string;   // العربية
  nameRu: string;   // Русский
  nameIt: string;   // Italiano
  nameNl: string;   // Nederlands
  flag: string;
  phoneCode: string;
  region: string;
  priority?: number;
  disabled?: boolean;
}

// 🌍 Tableau des pays avec noms dans 10 langues
export const countriesData: CountryData[] = [
  // ========================================
  // 🏆 TOP 6 PRIORITAIRES
  // ========================================
  { code: "GB", nameFr: "Royaume-Uni", nameEn: "United Kingdom", nameEs: "Reino Unido", nameDe: "Vereinigtes Königreich", namePt: "Reino Unido", nameZh: "英国", nameAr: "المملكة المتحدة", nameRu: "Великобритания", nameIt: "Regno Unito", nameNl: "Verenigd Koninkrijk", flag: "🇬🇧", phoneCode: "+44", region: "Europe", priority: 1 },
  { code: "FR", nameFr: "France", nameEn: "France", nameEs: "Francia", nameDe: "Frankreich", namePt: "França", nameZh: "法国", nameAr: "فرنسا", nameRu: "Франция", nameIt: "Francia", nameNl: "Frankrijk", flag: "🇫🇷", phoneCode: "+33", region: "Europe", priority: 1 },
  { code: "DE", nameFr: "Allemagne", nameEn: "Germany", nameEs: "Alemania", nameDe: "Deutschland", namePt: "Alemanha", nameZh: "德国", nameAr: "ألمانيا", nameRu: "Германия", nameIt: "Germania", nameNl: "Duitsland", flag: "🇩🇪", phoneCode: "+49", region: "Europe", priority: 1 },
  { code: "ES", nameFr: "Espagne", nameEn: "Spain", nameEs: "España", nameDe: "Spanien", namePt: "Espanha", nameZh: "西班牙", nameAr: "إسبانيا", nameRu: "Испания", nameIt: "Spagna", nameNl: "Spanje", flag: "🇪🇸", phoneCode: "+34", region: "Europe", priority: 1 },
  { code: "RU", nameFr: "Russie", nameEn: "Russia", nameEs: "Rusia", nameDe: "Russland", namePt: "Rússia", nameZh: "俄罗斯", nameAr: "روسيا", nameRu: "Россия", nameIt: "Russia", nameNl: "Rusland", flag: "🇷🇺", phoneCode: "+7", region: "Europe", priority: 1 },
  { code: "CN", nameFr: "Chine", nameEn: "China", nameEs: "China", nameDe: "China", namePt: "China", nameZh: "中国", nameAr: "الصين", nameRu: "Китай", nameIt: "Cina", nameNl: "China", flag: "🇨🇳", phoneCode: "+86", region: "Asia", priority: 1 },

  // ========================================
  // 📍 SÉPARATEUR VISUEL
  // ========================================
  { code: "SEPARATOR", nameFr: "─────────────────", nameEn: "─────────────────", nameEs: "─────────────────", nameDe: "─────────────────", namePt: "─────────────────", nameZh: "─────────────────", nameAr: "─────────────────", nameRu: "─────────────────", nameIt: "─────────────────", nameNl: "─────────────────", flag: "", phoneCode: "", region: "", priority: 2, disabled: true },

  // ========================================
  // 🌍 TOUS LES AUTRES PAYS (A→Z par nom anglais)
  // ========================================
  { code: "AF", nameFr: "Afghanistan", nameEn: "Afghanistan", nameEs: "Afganistán", nameDe: "Afghanistan", namePt: "Afeganistão", nameZh: "阿富汗", nameAr: "أفغانستان", nameRu: "Афганистан", nameIt: "Afghanistan", nameNl: "Afghanistan", flag: "🇦🇫", phoneCode: "+93", region: "Asia", priority: 3 },
  { code: "AL", nameFr: "Albanie", nameEn: "Albania", nameEs: "Albania", nameDe: "Albanien", namePt: "Albânia", nameZh: "阿尔巴尼亚", nameAr: "ألبانيا", nameRu: "Албания", nameIt: "Albania", nameNl: "Albanië", flag: "🇦🇱", phoneCode: "+355", region: "Europe", priority: 3 },
  { code: "DZ", nameFr: "Algérie", nameEn: "Algeria", nameEs: "Argelia", nameDe: "Algerien", namePt: "Argélia", nameZh: "阿尔及利亚", nameAr: "الجزائر", nameRu: "Алжир", nameIt: "Algeria", nameNl: "Algerije", flag: "🇩🇿", phoneCode: "+213", region: "Africa", priority: 3 },
  { code: "AD", nameFr: "Andorre", nameEn: "Andorra", nameEs: "Andorra", nameDe: "Andorra", namePt: "Andorra", nameZh: "安道尔", nameAr: "أندورا", nameRu: "Андорра", nameIt: "Andorra", nameNl: "Andorra", flag: "🇦🇩", phoneCode: "+376", region: "Europe", priority: 3 },
  { code: "AO", nameFr: "Angola", nameEn: "Angola", nameEs: "Angola", nameDe: "Angola", namePt: "Angola", nameZh: "安哥拉", nameAr: "أنغولا", nameRu: "Ангола", nameIt: "Angola", nameNl: "Angola", flag: "🇦🇴", phoneCode: "+244", region: "Africa", priority: 3 },
  { code: "AG", nameFr: "Antigua-et-Barbuda", nameEn: "Antigua and Barbuda", nameEs: "Antigua y Barbuda", nameDe: "Antigua und Barbuda", namePt: "Antígua e Barbuda", nameZh: "安提瓜和巴布达", nameAr: "أنتيغوا وبربودا", nameRu: "Антигуа и Барбуда", nameIt: "Antigua e Barbuda", nameNl: "Antigua en Barbuda", flag: "🇦🇬", phoneCode: "+1268", region: "Caribbean", priority: 3 },
  { code: "AR", nameFr: "Argentine", nameEn: "Argentina", nameEs: "Argentina", nameDe: "Argentinien", namePt: "Argentina", nameZh: "阿根廷", nameAr: "الأرجنتين", nameRu: "Аргентина", nameIt: "Argentina", nameNl: "Argentinië", flag: "🇦🇷", phoneCode: "+54", region: "South America", priority: 3 },
  { code: "AM", nameFr: "Arménie", nameEn: "Armenia", nameEs: "Armenia", nameDe: "Armenien", namePt: "Armênia", nameZh: "亚美尼亚", nameAr: "أرمينيا", nameRu: "Армения", nameIt: "Armenia", nameNl: "Armenië", flag: "🇦🇲", phoneCode: "+374", region: "Asia", priority: 3 },
  { code: "AU", nameFr: "Australie", nameEn: "Australia", nameEs: "Australia", nameDe: "Australien", namePt: "Austrália", nameZh: "澳大利亚", nameAr: "أستراليا", nameRu: "Австралия", nameIt: "Australia", nameNl: "Australië", flag: "🇦🇺", phoneCode: "+61", region: "Oceania", priority: 3 },
  { code: "AT", nameFr: "Autriche", nameEn: "Austria", nameEs: "Austria", nameDe: "Österreich", namePt: "Áustria", nameZh: "奥地利", nameAr: "النمسا", nameRu: "Австрия", nameIt: "Austria", nameNl: "Oostenrijk", flag: "🇦🇹", phoneCode: "+43", region: "Europe", priority: 3 },
  { code: "AZ", nameFr: "Azerbaïdjan", nameEn: "Azerbaijan", nameEs: "Azerbaiyán", nameDe: "Aserbaidschan", namePt: "Azerbaijão", nameZh: "阿塞拜疆", nameAr: "أذربيجان", nameRu: "Азербайджан", nameIt: "Azerbaigian", nameNl: "Azerbeidzjan", flag: "🇦🇿", phoneCode: "+994", region: "Asia", priority: 3 },
  { code: "BS", nameFr: "Bahamas", nameEn: "Bahamas", nameEs: "Bahamas", nameDe: "Bahamas", namePt: "Bahamas", nameZh: "巴哈马", nameAr: "باهاماس", nameRu: "Багамы", nameIt: "Bahamas", nameNl: "Bahama's", flag: "🇧🇸", phoneCode: "+1242", region: "Caribbean", priority: 3 },
  { code: "BH", nameFr: "Bahreïn", nameEn: "Bahrain", nameEs: "Baréin", nameDe: "Bahrain", namePt: "Bahrein", nameZh: "巴林", nameAr: "البحرين", nameRu: "Бахрейн", nameIt: "Bahrain", nameNl: "Bahrein", flag: "🇧🇭", phoneCode: "+973", region: "Asia", priority: 3 },
  { code: "BD", nameFr: "Bangladesh", nameEn: "Bangladesh", nameEs: "Bangladés", nameDe: "Bangladesch", namePt: "Bangladesh", nameZh: "孟加拉国", nameAr: "بنغلاديش", nameRu: "Бангладеш", nameIt: "Bangladesh", nameNl: "Bangladesh", flag: "🇧🇩", phoneCode: "+880", region: "Asia", priority: 3 },
  { code: "BB", nameFr: "Barbade", nameEn: "Barbados", nameEs: "Barbados", nameDe: "Barbados", namePt: "Barbados", nameZh: "巴巴多斯", nameAr: "بربادوس", nameRu: "Барбадос", nameIt: "Barbados", nameNl: "Barbados", flag: "🇧🇧", phoneCode: "+1246", region: "Caribbean", priority: 3 },
  { code: "BY", nameFr: "Biélorussie", nameEn: "Belarus", nameEs: "Bielorrusia", nameDe: "Weißrussland", namePt: "Belarus", nameZh: "白俄罗斯", nameAr: "بيلاروس", nameRu: "Беларусь", nameIt: "Bielorussia", nameNl: "Wit-Rusland", flag: "🇧🇾", phoneCode: "+375", region: "Europe", priority: 3 },
  { code: "BE", nameFr: "Belgique", nameEn: "Belgium", nameEs: "Bélgica", nameDe: "Belgien", namePt: "Bélgica", nameZh: "比利时", nameAr: "بلجيكا", nameRu: "Бельгия", nameIt: "Belgio", nameNl: "België", flag: "🇧🇪", phoneCode: "+32", region: "Europe", priority: 3 },
  { code: "BZ", nameFr: "Belize", nameEn: "Belize", nameEs: "Belice", nameDe: "Belize", namePt: "Belize", nameZh: "伯利兹", nameAr: "بليز", nameRu: "Белиз", nameIt: "Belize", nameNl: "Belize", flag: "🇧🇿", phoneCode: "+501", region: "Central America", priority: 3 },
  { code: "BJ", nameFr: "Bénin", nameEn: "Benin", nameEs: "Benín", nameDe: "Benin", namePt: "Benin", nameZh: "贝宁", nameAr: "بنين", nameRu: "Бенин", nameIt: "Benin", nameNl: "Benin", flag: "🇧🇯", phoneCode: "+229", region: "Africa", priority: 3 },
  { code: "BT", nameFr: "Bhoutan", nameEn: "Bhutan", nameEs: "Bután", nameDe: "Bhutan", namePt: "Butão", nameZh: "不丹", nameAr: "بوتان", nameRu: "Бутан", nameIt: "Bhutan", nameNl: "Bhutan", flag: "🇧🇹", phoneCode: "+975", region: "Asia", priority: 3 },
  { code: "BO", nameFr: "Bolivie", nameEn: "Bolivia", nameEs: "Bolivia", nameDe: "Bolivien", namePt: "Bolívia", nameZh: "玻利维亚", nameAr: "بوليفيا", nameRu: "Боливия", nameIt: "Bolivia", nameNl: "Bolivia", flag: "🇧🇴", phoneCode: "+591", region: "South America", priority: 3 },
  { code: "BA", nameFr: "Bosnie-Herzégovine", nameEn: "Bosnia and Herzegovina", nameEs: "Bosnia y Herzegovina", nameDe: "Bosnien und Herzegowina", namePt: "Bósnia e Herzegovina", nameZh: "波斯尼亚和黑塞哥维那", nameAr: "البوسنة والهرسك", nameRu: "Босния и Герцеговина", nameIt: "Bosnia ed Erzegovina", nameNl: "Bosnië en Herzegovina", flag: "🇧🇦", phoneCode: "+387", region: "Europe", priority: 3 },
  { code: "BW", nameFr: "Botswana", nameEn: "Botswana", nameEs: "Botsuana", nameDe: "Botswana", namePt: "Botsuana", nameZh: "博茨瓦纳", nameAr: "بوتسوانا", nameRu: "Ботсвана", nameIt: "Botswana", nameNl: "Botswana", flag: "🇧🇼", phoneCode: "+267", region: "Africa", priority: 3 },
  { code: "BR", nameFr: "Brésil", nameEn: "Brazil", nameEs: "Brasil", nameDe: "Brasilien", namePt: "Brasil", nameZh: "巴西", nameAr: "البرازيل", nameRu: "Бразилия", nameIt: "Brasile", nameNl: "Brazilië", flag: "🇧🇷", phoneCode: "+55", region: "South America", priority: 3 },
  { code: "BN", nameFr: "Brunéi", nameEn: "Brunei", nameEs: "Brunéi", nameDe: "Brunei", namePt: "Brunei", nameZh: "文莱", nameAr: "بروناي", nameRu: "Бруней", nameIt: "Brunei", nameNl: "Brunei", flag: "🇧🇳", phoneCode: "+673", region: "Asia", priority: 3 },
  { code: "BG", nameFr: "Bulgarie", nameEn: "Bulgaria", nameEs: "Bulgaria", nameDe: "Bulgarien", namePt: "Bulgária", nameZh: "保加利亚", nameAr: "بلغاريا", nameRu: "Болгария", nameIt: "Bulgaria", nameNl: "Bulgarije", flag: "🇧🇬", phoneCode: "+359", region: "Europe", priority: 3 },
  { code: "BF", nameFr: "Burkina Faso", nameEn: "Burkina Faso", nameEs: "Burkina Faso", nameDe: "Burkina Faso", namePt: "Burkina Faso", nameZh: "布基纳法索", nameAr: "بوركينا فاسو", nameRu: "Буркина-Фасо", nameIt: "Burkina Faso", nameNl: "Burkina Faso", flag: "🇧🇫", phoneCode: "+226", region: "Africa", priority: 3 },
  { code: "BI", nameFr: "Burundi", nameEn: "Burundi", nameEs: "Burundi", nameDe: "Burundi", namePt: "Burundi", nameZh: "布隆迪", nameAr: "بوروندي", nameRu: "Бурунди", nameIt: "Burundi", nameNl: "Burundi", flag: "🇧🇮", phoneCode: "+257", region: "Africa", priority: 3 },
  { code: "CV", nameFr: "Cap-Vert", nameEn: "Cape Verde", nameEs: "Cabo Verde", nameDe: "Kap Verde", namePt: "Cabo Verde", nameZh: "佛得角", nameAr: "الرأس الأخضر", nameRu: "Кабо-Верде", nameIt: "Capo Verde", nameNl: "Kaapverdië", flag: "🇨🇻", phoneCode: "+238", region: "Africa", priority: 3 },
  { code: "KH", nameFr: "Cambodge", nameEn: "Cambodia", nameEs: "Camboya", nameDe: "Kambodscha", namePt: "Camboja", nameZh: "柬埔寨", nameAr: "كمبوديا", nameRu: "Камбоджа", nameIt: "Cambogia", nameNl: "Cambodja", flag: "🇰🇭", phoneCode: "+855", region: "Asia", priority: 3 },
  { code: "CM", nameFr: "Cameroun", nameEn: "Cameroon", nameEs: "Camerún", nameDe: "Kamerun", namePt: "Camarões", nameZh: "喀麦隆", nameAr: "الكاميرون", nameRu: "Камерун", nameIt: "Camerun", nameNl: "Kameroen", flag: "🇨🇲", phoneCode: "+237", region: "Africa", priority: 3 },
  { code: "CA", nameFr: "Canada", nameEn: "Canada", nameEs: "Canadá", nameDe: "Kanada", namePt: "Canadá", nameZh: "加拿大", nameAr: "كندا", nameRu: "Канада", nameIt: "Canada", nameNl: "Canada", flag: "🇨🇦", phoneCode: "+1", region: "North America", priority: 3 },
  { code: "CF", nameFr: "République centrafricaine", nameEn: "Central African Republic", nameEs: "República Centroafricana", nameDe: "Zentralafrikanische Republik", namePt: "República Centro-Africana", nameZh: "中非共和国", nameAr: "جمهورية أفريقيا الوسطى", nameRu: "Центральноафриканская Республика", nameIt: "Repubblica Centrafricana", nameNl: "Centraal-Afrikaanse Republiek", flag: "🇨🇫", phoneCode: "+236", region: "Africa", priority: 3 },
  { code: "TD", nameFr: "Tchad", nameEn: "Chad", nameEs: "Chad", nameDe: "Tschad", namePt: "Chade", nameZh: "乍得", nameAr: "تشاد", nameRu: "Чад", nameIt: "Ciad", nameNl: "Tsjaad", flag: "🇹🇩", phoneCode: "+235", region: "Africa", priority: 3 },
  { code: "CL", nameFr: "Chili", nameEn: "Chile", nameEs: "Chile", nameDe: "Chile", namePt: "Chile", nameZh: "智利", nameAr: "تشيلي", nameRu: "Чили", nameIt: "Cile", nameNl: "Chili", flag: "🇨🇱", phoneCode: "+56", region: "South America", priority: 3 },
  { code: "CO", nameFr: "Colombie", nameEn: "Colombia", nameEs: "Colombia", nameDe: "Kolumbien", namePt: "Colômbia", nameZh: "哥伦比亚", nameAr: "كولومبيا", nameRu: "Колумбия", nameIt: "Colombia", nameNl: "Colombia", flag: "🇨🇴", phoneCode: "+57", region: "South America", priority: 3 },
  { code: "KM", nameFr: "Comores", nameEn: "Comoros", nameEs: "Comoras", nameDe: "Komoren", namePt: "Comores", nameZh: "科摩罗", nameAr: "جزر القمر", nameRu: "Коморы", nameIt: "Comore", nameNl: "Comoren", flag: "🇰🇲", phoneCode: "+269", region: "Africa", priority: 3 },
  { code: "CG", nameFr: "Congo", nameEn: "Congo", nameEs: "Congo", nameDe: "Republik Kongo", namePt: "Congo", nameZh: "刚果共和国", nameAr: "جمهورية الكونغو", nameRu: "Республика Конго", nameIt: "Congo", nameNl: "Congo", flag: "🇨🇬", phoneCode: "+242", region: "Africa", priority: 3 },
  { code: "CD", nameFr: "République démocratique du Congo", nameEn: "Congo (Democratic Republic)", nameEs: "República Democrática del Congo", nameDe: "Demokratische Republik Kongo", namePt: "República Democrática do Congo", nameZh: "刚果民主共和国", nameAr: "جمهورية الكونغو الديمقراطية", nameRu: "Демократическая Республика Конго", nameIt: "Repubblica Democratica del Congo", nameNl: "Democratische Republiek Congo", flag: "🇨🇩", phoneCode: "+243", region: "Africa", priority: 3 },
  { code: "CK", nameFr: "Îles Cook", nameEn: "Cook Islands", nameEs: "Islas Cook", nameDe: "Cookinseln", namePt: "Ilhas Cook", nameZh: "库克群岛", nameAr: "جزر كوك", nameRu: "Острова Кука", nameIt: "Isole Cook", nameNl: "Cookeilanden", flag: "🇨🇰", phoneCode: "+682", region: "Oceania", priority: 3 },
  { code: "CR", nameFr: "Costa Rica", nameEn: "Costa Rica", nameEs: "Costa Rica", nameDe: "Costa Rica", namePt: "Costa Rica", nameZh: "哥斯达黎加", nameAr: "كوستاريكا", nameRu: "Коста-Рика", nameIt: "Costa Rica", nameNl: "Costa Rica", flag: "🇨🇷", phoneCode: "+506", region: "Central America", priority: 3 },
  { code: "CI", nameFr: "Côte d'Ivoire", nameEn: "Côte d'Ivoire", nameEs: "Costa de Marfil", nameDe: "Elfenbeinküste", namePt: "Costa do Marfim", nameZh: "科特迪瓦", nameAr: "ساحل العاج", nameRu: "Кот-д'Ивуар", nameIt: "Costa d'Avorio", nameNl: "Ivoorkust", flag: "🇨🇮", phoneCode: "+225", region: "Africa", priority: 3 },
  { code: "HR", nameFr: "Croatie", nameEn: "Croatia", nameEs: "Croacia", nameDe: "Kroatien", namePt: "Croácia", nameZh: "克罗地亚", nameAr: "كرواتيا", nameRu: "Хорватия", nameIt: "Croazia", nameNl: "Kroatië", flag: "🇭🇷", phoneCode: "+385", region: "Europe", priority: 3 },
  { code: "CU", nameFr: "Cuba", nameEn: "Cuba", nameEs: "Cuba", nameDe: "Kuba", namePt: "Cuba", nameZh: "古巴", nameAr: "كوبا", nameRu: "Куба", nameIt: "Cuba", nameNl: "Cuba", flag: "🇨🇺", phoneCode: "+53", region: "Caribbean", priority: 3 },
  { code: "CY", nameFr: "Chypre", nameEn: "Cyprus", nameEs: "Chipre", nameDe: "Zypern", namePt: "Chipre", nameZh: "塞浦路斯", nameAr: "قبرص", nameRu: "Кипр", nameIt: "Cipro", nameNl: "Cyprus", flag: "🇨🇾", phoneCode: "+357", region: "Europe", priority: 3 },
  { code: "CZ", nameFr: "République tchèque", nameEn: "Czech Republic", nameEs: "República Checa", nameDe: "Tschechien", namePt: "República Checa", nameZh: "捷克共和国", nameAr: "التشيك", nameRu: "Чехия", nameIt: "Repubblica Ceca", nameNl: "Tsjechië", flag: "🇨🇿", phoneCode: "+420", region: "Europe", priority: 3 },
  { code: "DK", nameFr: "Danemark", nameEn: "Denmark", nameEs: "Dinamarca", nameDe: "Dänemark", namePt: "Dinamarca", nameZh: "丹麦", nameAr: "الدنمارك", nameRu: "Дания", nameIt: "Danimarca", nameNl: "Denemarken", flag: "🇩🇰", phoneCode: "+45", region: "Europe", priority: 3 },
  { code: "DJ", nameFr: "Djibouti", nameEn: "Djibouti", nameEs: "Yibuti", nameDe: "Dschibuti", namePt: "Djibuti", nameZh: "吉布提", nameAr: "جيبوتي", nameRu: "Джибути", nameIt: "Gibuti", nameNl: "Djibouti", flag: "🇩🇯", phoneCode: "+253", region: "Africa", priority: 3 },
  { code: "DM", nameFr: "Dominique", nameEn: "Dominica", nameEs: "Dominica", nameDe: "Dominica", namePt: "Dominica", nameZh: "多米尼克", nameAr: "دومينيكا", nameRu: "Доминика", nameIt: "Dominica", nameNl: "Dominica", flag: "🇩🇲", phoneCode: "+1767", region: "Caribbean", priority: 3 },
  { code: "DO", nameFr: "République dominicaine", nameEn: "Dominican Republic", nameEs: "República Dominicana", nameDe: "Dominikanische Republik", namePt: "República Dominicana", nameZh: "多米尼加共和国", nameAr: "جمهورية الدومينيكان", nameRu: "Доминиканская Республика", nameIt: "Repubblica Dominicana", nameNl: "Dominicaanse Republiek", flag: "🇩🇴", phoneCode: "+1809", region: "Caribbean", priority: 3 },
  { code: "EC", nameFr: "Équateur", nameEn: "Ecuador", nameEs: "Ecuador", nameDe: "Ecuador", namePt: "Equador", nameZh: "厄瓜多尔", nameAr: "الإكوادور", nameRu: "Эквадор", nameIt: "Ecuador", nameNl: "Ecuador", flag: "🇪🇨", phoneCode: "+593", region: "South America", priority: 3 },
  { code: "EG", nameFr: "Égypte", nameEn: "Egypt", nameEs: "Egipto", nameDe: "Ägypten", namePt: "Egito", nameZh: "埃及", nameAr: "مصر", nameRu: "Египет", nameIt: "Egitto", nameNl: "Egypte", flag: "🇪🇬", phoneCode: "+20", region: "Africa", priority: 3 },
  { code: "SV", nameFr: "Salvador", nameEn: "El Salvador", nameEs: "El Salvador", nameDe: "El Salvador", namePt: "El Salvador", nameZh: "萨尔瓦多", nameAr: "السلفادور", nameRu: "Сальвадор", nameIt: "El Salvador", nameNl: "El Salvador", flag: "🇸🇻", phoneCode: "+503", region: "Central America", priority: 3 },
  { code: "GQ", nameFr: "Guinée équatoriale", nameEn: "Equatorial Guinea", nameEs: "Guinea Ecuatorial", nameDe: "Äquatorialguinea", namePt: "Guiné Equatorial", nameZh: "赤道几内亚", nameAr: "غينيا الاستوائية", nameRu: "Экваториальная Гвинея", nameIt: "Guinea Equatoriale", nameNl: "Equatoriaal-Guinea", flag: "🇬🇶", phoneCode: "+240", region: "Africa", priority: 3 },
  { code: "ER", nameFr: "Érythrée", nameEn: "Eritrea", nameEs: "Eritrea", nameDe: "Eritrea", namePt: "Eritreia", nameZh: "厄立特里亚", nameAr: "إريتريا", nameRu: "Эритрея", nameIt: "Eritrea", nameNl: "Eritrea", flag: "🇪🇷", phoneCode: "+291", region: "Africa", priority: 3 },
  { code: "EE", nameFr: "Estonie", nameEn: "Estonia", nameEs: "Estonia", nameDe: "Estland", namePt: "Estônia", nameZh: "爱沙尼亚", nameAr: "إستونيا", nameRu: "Эстония", nameIt: "Estonia", nameNl: "Estland", flag: "🇪🇪", phoneCode: "+372", region: "Europe", priority: 3 },
  { code: "SZ", nameFr: "Eswatini", nameEn: "Eswatini", nameEs: "Esuatini", nameDe: "Eswatini", namePt: "Eswatini", nameZh: "斯威士兰", nameAr: "إسواتيني", nameRu: "Эсватини", nameIt: "Eswatini", nameNl: "Eswatini", flag: "🇸🇿", phoneCode: "+268", region: "Africa", priority: 3 },
  { code: "ET", nameFr: "Éthiopie", nameEn: "Ethiopia", nameEs: "Etiopía", nameDe: "Äthiopien", namePt: "Etiópia", nameZh: "埃塞俄比亚", nameAr: "إثيوبيا", nameRu: "Эфиопия", nameIt: "Etiopia", nameNl: "Ethiopië", flag: "🇪🇹", phoneCode: "+251", region: "Africa", priority: 3 },
  { code: "FJ", nameFr: "Fidji", nameEn: "Fiji", nameEs: "Fiyi", nameDe: "Fidschi", namePt: "Fiji", nameZh: "斐济", nameAr: "فيجي", nameRu: "Фиджи", nameIt: "Fiji", nameNl: "Fiji", flag: "🇫🇯", phoneCode: "+679", region: "Oceania", priority: 3 },
  { code: "FI", nameFr: "Finlande", nameEn: "Finland", nameEs: "Finlandia", nameDe: "Finnland", namePt: "Finlândia", nameZh: "芬兰", nameAr: "فنلندا", nameRu: "Финляндия", nameIt: "Finlandia", nameNl: "Finland", flag: "🇫🇮", phoneCode: "+358", region: "Europe", priority: 3 },
  { code: "GA", nameFr: "Gabon", nameEn: "Gabon", nameEs: "Gabón", nameDe: "Gabun", namePt: "Gabão", nameZh: "加蓬", nameAr: "الغابون", nameRu: "Габон", nameIt: "Gabon", nameNl: "Gabon", flag: "🇬🇦", phoneCode: "+241", region: "Africa", priority: 3 },
  { code: "GM", nameFr: "Gambie", nameEn: "Gambia", nameEs: "Gambia", nameDe: "Gambia", namePt: "Gâmbia", nameZh: "冈比亚", nameAr: "غامبيا", nameRu: "Гамбия", nameIt: "Gambia", nameNl: "Gambia", flag: "🇬🇲", phoneCode: "+220", region: "Africa", priority: 3 },
  { code: "GE", nameFr: "Géorgie", nameEn: "Georgia", nameEs: "Georgia", nameDe: "Georgien", namePt: "Geórgia", nameZh: "格鲁吉亚", nameAr: "جورجيا", nameRu: "Грузия", nameIt: "Georgia", nameNl: "Georgië", flag: "🇬🇪", phoneCode: "+995", region: "Asia", priority: 3 },
  { code: "GF", nameFr: "Guyane française", nameEn: "French Guiana", nameEs: "Guayana Francesa", nameDe: "Französisch-Guayana", namePt: "Guiana Francesa", nameZh: "法属圭亚那", nameAr: "غويانا الفرنسية", nameRu: "Французская Гвиана", nameIt: "Guyana francese", nameNl: "Frans-Guyana", flag: "🇬🇫", phoneCode: "+594", region: "South America", priority: 3 },
  { code: "GH", nameFr: "Ghana", nameEn: "Ghana", nameEs: "Ghana", nameDe: "Ghana", namePt: "Gana", nameZh: "加纳", nameAr: "غانا", nameRu: "Гана", nameIt: "Ghana", nameNl: "Ghana", flag: "🇬🇭", phoneCode: "+233", region: "Africa", priority: 3 },
  { code: "GR", nameFr: "Grèce", nameEn: "Greece", nameEs: "Grecia", nameDe: "Griechenland", namePt: "Grécia", nameZh: "希腊", nameAr: "اليونان", nameRu: "Греция", nameIt: "Grecia", nameNl: "Griekenland", flag: "🇬🇷", phoneCode: "+30", region: "Europe", priority: 3 },
  { code: "GD", nameFr: "Grenade", nameEn: "Grenada", nameEs: "Granada", nameDe: "Grenada", namePt: "Granada", nameZh: "格林纳达", nameAr: "غرينادا", nameRu: "Гренада", nameIt: "Grenada", nameNl: "Grenada", flag: "🇬🇩", phoneCode: "+1473", region: "Caribbean", priority: 3 },
  { code: "GT", nameFr: "Guatemala", nameEn: "Guatemala", nameEs: "Guatemala", nameDe: "Guatemala", namePt: "Guatemala", nameZh: "危地马拉", nameAr: "غواتيمالا", nameRu: "Гватемала", nameIt: "Guatemala", nameNl: "Guatemala", flag: "🇬🇹", phoneCode: "+502", region: "Central America", priority: 3 },
  { code: "GN", nameFr: "Guinée", nameEn: "Guinea", nameEs: "Guinea", nameDe: "Guinea", namePt: "Guiné", nameZh: "几内亚", nameAr: "غينيا", nameRu: "Гвинея", nameIt: "Guinea", nameNl: "Guinea", flag: "🇬🇳", phoneCode: "+224", region: "Africa", priority: 3 },
  { code: "GW", nameFr: "Guinée-Bissau", nameEn: "Guinea-Bissau", nameEs: "Guinea-Bisáu", nameDe: "Guinea-Bissau", namePt: "Guiné-Bissau", nameZh: "几内亚比绍", nameAr: "غينيا بيساو", nameRu: "Гвинея-Бисау", nameIt: "Guinea-Bissau", nameNl: "Guinee-Bissau", flag: "🇬🇼", phoneCode: "+245", region: "Africa", priority: 3 },
  { code: "GY", nameFr: "Guyana", nameEn: "Guyana", nameEs: "Guyana", nameDe: "Guyana", namePt: "Guiana", nameZh: "圭亚那", nameAr: "غيانا", nameRu: "Гайана", nameIt: "Guyana", nameNl: "Guyana", flag: "🇬🇾", phoneCode: "+592", region: "South America", priority: 3 },
  { code: "HT", nameFr: "Haïti", nameEn: "Haiti", nameEs: "Haití", nameDe: "Haiti", namePt: "Haiti", nameZh: "海地", nameAr: "هايتي", nameRu: "Гаити", nameIt: "Haiti", nameNl: "Haïti", flag: "🇭🇹", phoneCode: "+509", region: "Caribbean", priority: 3 },
  { code: "HN", nameFr: "Honduras", nameEn: "Honduras", nameEs: "Honduras", nameDe: "Honduras", namePt: "Honduras", nameZh: "洪都拉斯", nameAr: "هندوراس", nameRu: "Гондурас", nameIt: "Honduras", nameNl: "Honduras", flag: "🇭🇳", phoneCode: "+504", region: "Central America", priority: 3 },
  { code: "HK", nameFr: "Hong Kong", nameEn: "Hong Kong", nameEs: "Hong Kong", nameDe: "Hongkong", namePt: "Hong Kong", nameZh: "香港", nameAr: "هونغ كونغ", nameRu: "Гонконг", nameIt: "Hong Kong", nameNl: "Hongkong", flag: "🇭🇰", phoneCode: "+852", region: "Asia", priority: 3 },
  { code: "HU", nameFr: "Hongrie", nameEn: "Hungary", nameEs: "Hungría", nameDe: "Ungarn", namePt: "Hungria", nameZh: "匈牙利", nameAr: "المجر", nameRu: "Венгрия", nameIt: "Ungheria", nameNl: "Hongarije", flag: "🇭🇺", phoneCode: "+36", region: "Europe", priority: 3 },
  { code: "IS", nameFr: "Islande", nameEn: "Iceland", nameEs: "Islandia", nameDe: "Island", namePt: "Islândia", nameZh: "冰岛", nameAr: "أيسلندا", nameRu: "Исландия", nameIt: "Islanda", nameNl: "IJsland", flag: "🇮🇸", phoneCode: "+354", region: "Europe", priority: 3 },
  { code: "IN", nameFr: "Inde", nameEn: "India", nameEs: "India", nameDe: "Indien", namePt: "Índia", nameZh: "印度", nameAr: "الهند", nameRu: "Индия", nameIt: "India", nameNl: "India", flag: "🇮🇳", phoneCode: "+91", region: "Asia", priority: 3 },
  { code: "ID", nameFr: "Indonésie", nameEn: "Indonesia", nameEs: "Indonesia", nameDe: "Indonesien", namePt: "Indonésia", nameZh: "印度尼西亚", nameAr: "إندونيسيا", nameRu: "Индонезия", nameIt: "Indonesia", nameNl: "Indonesië", flag: "🇮🇩", phoneCode: "+62", region: "Asia", priority: 3 },
  { code: "IR", nameFr: "Iran", nameEn: "Iran", nameEs: "Irán", nameDe: "Iran", namePt: "Irã", nameZh: "伊朗", nameAr: "إيران", nameRu: "Иран", nameIt: "Iran", nameNl: "Iran", flag: "🇮🇷", phoneCode: "+98", region: "Asia", priority: 3 },
  { code: "IQ", nameFr: "Irak", nameEn: "Iraq", nameEs: "Irak", nameDe: "Irak", namePt: "Iraque", nameZh: "伊拉克", nameAr: "العراق", nameRu: "Ирак", nameIt: "Iraq", nameNl: "Irak", flag: "🇮🇶", phoneCode: "+964", region: "Asia", priority: 3 },
  { code: "IE", nameFr: "Irlande", nameEn: "Ireland", nameEs: "Irlanda", nameDe: "Irland", namePt: "Irlanda", nameZh: "爱尔兰", nameAr: "أيرلندا", nameRu: "Ирландия", nameIt: "Irlanda", nameNl: "Ierland", flag: "🇮🇪", phoneCode: "+353", region: "Europe", priority: 3 },
  { code: "IL", nameFr: "Israël", nameEn: "Israel", nameEs: "Israel", nameDe: "Israel", namePt: "Israel", nameZh: "以色列", nameAr: "إسرائيل", nameRu: "Израиль", nameIt: "Israele", nameNl: "Israël", flag: "🇮🇱", phoneCode: "+972", region: "Asia", priority: 3 },
  { code: "IT", nameFr: "Italie", nameEn: "Italy", nameEs: "Italia", nameDe: "Italien", namePt: "Itália", nameZh: "意大利", nameAr: "إيطاليا", nameRu: "Италия", nameIt: "Italia", nameNl: "Italië", flag: "🇮🇹", phoneCode: "+39", region: "Europe", priority: 3 },
  { code: "JM", nameFr: "Jamaïque", nameEn: "Jamaica", nameEs: "Jamaica", nameDe: "Jamaika", namePt: "Jamaica", nameZh: "牙买加", nameAr: "جامايكا", nameRu: "Ямайка", nameIt: "Giamaica", nameNl: "Jamaica", flag: "🇯🇲", phoneCode: "+1876", region: "Caribbean", priority: 3 },
  { code: "JP", nameFr: "Japon", nameEn: "Japan", nameEs: "Japón", nameDe: "Japan", namePt: "Japão", nameZh: "日本", nameAr: "اليابان", nameRu: "Япония", nameIt: "Giappone", nameNl: "Japan", flag: "🇯🇵", phoneCode: "+81", region: "Asia", priority: 3 },
  { code: "JO", nameFr: "Jordanie", nameEn: "Jordan", nameEs: "Jordania", nameDe: "Jordanien", namePt: "Jordânia", nameZh: "约旦", nameAr: "الأردن", nameRu: "Иордания", nameIt: "Giordania", nameNl: "Jordanië", flag: "🇯🇴", phoneCode: "+962", region: "Asia", priority: 3 },
  { code: "KZ", nameFr: "Kazakhstan", nameEn: "Kazakhstan", nameEs: "Kazajistán", nameDe: "Kasachstan", namePt: "Cazaquistão", nameZh: "哈萨克斯坦", nameAr: "كازاخستان", nameRu: "Казахстан", nameIt: "Kazakistan", nameNl: "Kazachstan", flag: "🇰🇿", phoneCode: "+7", region: "Asia", priority: 3 },
  { code: "KE", nameFr: "Kenya", nameEn: "Kenya", nameEs: "Kenia", nameDe: "Kenia", namePt: "Quênia", nameZh: "肯尼亚", nameAr: "كينيا", nameRu: "Кения", nameIt: "Kenya", nameNl: "Kenia", flag: "🇰🇪", phoneCode: "+254", region: "Africa", priority: 3 },
  { code: "KI", nameFr: "Kiribati", nameEn: "Kiribati", nameEs: "Kiribati", nameDe: "Kiribati", namePt: "Kiribati", nameZh: "基里巴斯", nameAr: "كيريباتي", nameRu: "Кирибати", nameIt: "Kiribati", nameNl: "Kiribati", flag: "🇰🇮", phoneCode: "+686", region: "Oceania", priority: 3 },
  { code: "KP", nameFr: "Corée du Nord", nameEn: "Korea (North)", nameEs: "Corea del Norte", nameDe: "Nordkorea", namePt: "Coreia do Norte", nameZh: "朝鲜", nameAr: "كوريا الشمالية", nameRu: "Северная Корея", nameIt: "Corea del Nord", nameNl: "Noord-Korea", flag: "🇰🇵", phoneCode: "+850", region: "Asia", priority: 3 },
  { code: "KR", nameFr: "Corée du Sud", nameEn: "Korea (South)", nameEs: "Corea del Sur", nameDe: "Südkorea", namePt: "Coreia do Sul", nameZh: "韩国", nameAr: "كوريا الجنوبية", nameRu: "Южная Корея", nameIt: "Corea del Sud", nameNl: "Zuid-Korea", flag: "🇰🇷", phoneCode: "+82", region: "Asia", priority: 3 },
  { code: "XK", nameFr: "Kosovo", nameEn: "Kosovo", nameEs: "Kosovo", nameDe: "Kosovo", namePt: "Kosovo", nameZh: "科索沃", nameAr: "كوسوفو", nameRu: "Косово", nameIt: "Kosovo", nameNl: "Kosovo", flag: "🇽🇰", phoneCode: "+383", region: "Europe", priority: 3 },
  { code: "KW", nameFr: "Koweït", nameEn: "Kuwait", nameEs: "Kuwait", nameDe: "Kuwait", namePt: "Kuwait", nameZh: "科威特", nameAr: "الكويت", nameRu: "Кувейт", nameIt: "Kuwait", nameNl: "Koeweit", flag: "🇰🇼", phoneCode: "+965", region: "Asia", priority: 3 },
  { code: "KG", nameFr: "Kirghizistan", nameEn: "Kyrgyzstan", nameEs: "Kirguistán", nameDe: "Kirgisistan", namePt: "Quirguistão", nameZh: "吉尔吉斯斯坦", nameAr: "قيرغيزستان", nameRu: "Киргизия", nameIt: "Kirghizistan", nameNl: "Kirgizië", flag: "🇰🇬", phoneCode: "+996", region: "Asia", priority: 3 },
  { code: "LA", nameFr: "Laos", nameEn: "Laos", nameEs: "Laos", nameDe: "Laos", namePt: "Laos", nameZh: "老挝", nameAr: "لاوس", nameRu: "Лаос", nameIt: "Laos", nameNl: "Laos", flag: "🇱🇦", phoneCode: "+856", region: "Asia", priority: 3 },
  { code: "LV", nameFr: "Lettonie", nameEn: "Latvia", nameEs: "Letonia", nameDe: "Lettland", namePt: "Letônia", nameZh: "拉脱维亚", nameAr: "لاتفيا", nameRu: "Латвия", nameIt: "Lettonia", nameNl: "Letland", flag: "🇱🇻", phoneCode: "+371", region: "Europe", priority: 3 },
  { code: "LB", nameFr: "Liban", nameEn: "Lebanon", nameEs: "Líbano", nameDe: "Libanon", namePt: "Líbano", nameZh: "黎巴嫩", nameAr: "لبنان", nameRu: "Ливан", nameIt: "Libano", nameNl: "Libanon", flag: "🇱🇧", phoneCode: "+961", region: "Asia", priority: 3 },
  { code: "LS", nameFr: "Lesotho", nameEn: "Lesotho", nameEs: "Lesoto", nameDe: "Lesotho", namePt: "Lesoto", nameZh: "莱索托", nameAr: "ليسوتو", nameRu: "Лесото", nameIt: "Lesotho", nameNl: "Lesotho", flag: "🇱🇸", phoneCode: "+266", region: "Africa", priority: 3 },
  { code: "LR", nameFr: "Libéria", nameEn: "Liberia", nameEs: "Liberia", nameDe: "Liberia", namePt: "Libéria", nameZh: "利比里亚", nameAr: "ليبيريا", nameRu: "Либерия", nameIt: "Liberia", nameNl: "Liberia", flag: "🇱🇷", phoneCode: "+231", region: "Africa", priority: 3 },
  { code: "LY", nameFr: "Libye", nameEn: "Libya", nameEs: "Libia", nameDe: "Libyen", namePt: "Líbia", nameZh: "利比亚", nameAr: "ليبيا", nameRu: "Ливия", nameIt: "Libia", nameNl: "Libië", flag: "🇱🇾", phoneCode: "+218", region: "Africa", priority: 3 },
  { code: "LI", nameFr: "Liechtenstein", nameEn: "Liechtenstein", nameEs: "Liechtenstein", nameDe: "Liechtenstein", namePt: "Liechtenstein", nameZh: "列支敦士登", nameAr: "ليختنشتاين", nameRu: "Лихтенштейн", nameIt: "Liechtenstein", nameNl: "Liechtenstein", flag: "🇱🇮", phoneCode: "+423", region: "Europe", priority: 3 },
  { code: "LT", nameFr: "Lituanie", nameEn: "Lithuania", nameEs: "Lituania", nameDe: "Litauen", namePt: "Lituânia", nameZh: "立陶宛", nameAr: "ليتوانيا", nameRu: "Литва", nameIt: "Lituania", nameNl: "Litouwen", flag: "🇱🇹", phoneCode: "+370", region: "Europe", priority: 3 },
  { code: "LU", nameFr: "Luxembourg", nameEn: "Luxembourg", nameEs: "Luxemburgo", nameDe: "Luxemburg", namePt: "Luxemburgo", nameZh: "卢森堡", nameAr: "لوكسمبورغ", nameRu: "Люксембург", nameIt: "Lussemburgo", nameNl: "Luxemburg", flag: "🇱🇺", phoneCode: "+352", region: "Europe", priority: 3 },
  { code: "MO", nameFr: "Macao", nameEn: "Macau", nameEs: "Macao", nameDe: "Macau", namePt: "Macau", nameZh: "澳门", nameAr: "ماكاو", nameRu: "Макао", nameIt: "Macao", nameNl: "Macau", flag: "🇲🇴", phoneCode: "+853", region: "Asia", priority: 3 },
  { code: "MG", nameFr: "Madagascar", nameEn: "Madagascar", nameEs: "Madagascar", nameDe: "Madagaskar", namePt: "Madagascar", nameZh: "马达加斯加", nameAr: "مدغشقر", nameRu: "Мадагаскар", nameIt: "Madagascar", nameNl: "Madagaskar", flag: "🇲🇬", phoneCode: "+261", region: "Africa", priority: 3 },
  { code: "MW", nameFr: "Malawi", nameEn: "Malawi", nameEs: "Malaui", nameDe: "Malawi", namePt: "Malawi", nameZh: "马拉维", nameAr: "مالاوي", nameRu: "Малави", nameIt: "Malawi", nameNl: "Malawi", flag: "🇲🇼", phoneCode: "+265", region: "Africa", priority: 3 },
  { code: "MY", nameFr: "Malaisie", nameEn: "Malaysia", nameEs: "Malasia", nameDe: "Malaysia", namePt: "Malásia", nameZh: "马来西亚", nameAr: "ماليزيا", nameRu: "Малайзия", nameIt: "Malesia", nameNl: "Maleisië", flag: "🇲🇾", phoneCode: "+60", region: "Asia", priority: 3 },
  { code: "MV", nameFr: "Maldives", nameEn: "Maldives", nameEs: "Maldivas", nameDe: "Malediven", namePt: "Maldivas", nameZh: "马尔代夫", nameAr: "المالديف", nameRu: "Мальдивы", nameIt: "Maldive", nameNl: "Maldiven", flag: "🇲🇻", phoneCode: "+960", region: "Asia", priority: 3 },
  { code: "ML", nameFr: "Mali", nameEn: "Mali", nameEs: "Malí", nameDe: "Mali", namePt: "Mali", nameZh: "马里", nameAr: "مالي", nameRu: "Мали", nameIt: "Mali", nameNl: "Mali", flag: "🇲🇱", phoneCode: "+223", region: "Africa", priority: 3 },
  { code: "MT", nameFr: "Malte", nameEn: "Malta", nameEs: "Malta", nameDe: "Malta", namePt: "Malta", nameZh: "马耳他", nameAr: "مالطا", nameRu: "Мальта", nameIt: "Malta", nameNl: "Malta", flag: "🇲🇹", phoneCode: "+356", region: "Europe", priority: 3 },
  { code: "MH", nameFr: "Îles Marshall", nameEn: "Marshall Islands", nameEs: "Islas Marshall", nameDe: "Marshallinseln", namePt: "Ilhas Marshall", nameZh: "马绍尔群岛", nameAr: "جزر مارشال", nameRu: "Маршалловы Острова", nameIt: "Isole Marshall", nameNl: "Marshalleilanden", flag: "🇲🇭", phoneCode: "+692", region: "Oceania", priority: 3 },
  { code: "MR", nameFr: "Mauritanie", nameEn: "Mauritania", nameEs: "Mauritania", nameDe: "Mauretanien", namePt: "Mauritânia", nameZh: "毛里塔尼亚", nameAr: "موريتانيا", nameRu: "Мавритания", nameIt: "Mauritania", nameNl: "Mauritanië", flag: "🇲🇷", phoneCode: "+222", region: "Africa", priority: 3 },
  { code: "MU", nameFr: "Maurice", nameEn: "Mauritius", nameEs: "Mauricio", nameDe: "Mauritius", namePt: "Maurício", nameZh: "毛里求斯", nameAr: "موريشيوس", nameRu: "Маврикий", nameIt: "Mauritius", nameNl: "Mauritius", flag: "🇲🇺", phoneCode: "+230", region: "Africa", priority: 3 },
  { code: "MX", nameFr: "Mexique", nameEn: "Mexico", nameEs: "México", nameDe: "Mexiko", namePt: "México", nameZh: "墨西哥", nameAr: "المكسيك", nameRu: "Мексика", nameIt: "Messico", nameNl: "Mexico", flag: "🇲🇽", phoneCode: "+52", region: "North America", priority: 3 },
  { code: "FM", nameFr: "Micronésie", nameEn: "Micronesia", nameEs: "Micronesia", nameDe: "Mikronesien", namePt: "Micronésia", nameZh: "密克罗尼西亚", nameAr: "ميكرونيزيا", nameRu: "Микронезия", nameIt: "Micronesia", nameNl: "Micronesië", flag: "🇫🇲", phoneCode: "+691", region: "Oceania", priority: 3 },
  { code: "MD", nameFr: "Moldavie", nameEn: "Moldova", nameEs: "Moldavia", nameDe: "Moldau", namePt: "Moldávia", nameZh: "摩尔多瓦", nameAr: "مولدوفا", nameRu: "Молдова", nameIt: "Moldavia", nameNl: "Moldavië", flag: "🇲🇩", phoneCode: "+373", region: "Europe", priority: 3 },
  { code: "MC", nameFr: "Monaco", nameEn: "Monaco", nameEs: "Mónaco", nameDe: "Monaco", namePt: "Mônaco", nameZh: "摩纳哥", nameAr: "موناكو", nameRu: "Монако", nameIt: "Monaco", nameNl: "Monaco", flag: "🇲🇨", phoneCode: "+377", region: "Europe", priority: 3 },
  { code: "MN", nameFr: "Mongolie", nameEn: "Mongolia", nameEs: "Mongolia", nameDe: "Mongolei", namePt: "Mongólia", nameZh: "蒙古", nameAr: "منغوليا", nameRu: "Монголия", nameIt: "Mongolia", nameNl: "Mongolië", flag: "🇲🇳", phoneCode: "+976", region: "Asia", priority: 3 },
  { code: "ME", nameFr: "Monténégro", nameEn: "Montenegro", nameEs: "Montenegro", nameDe: "Montenegro", namePt: "Montenegro", nameZh: "黑山", nameAr: "الجبل الأسود", nameRu: "Черногория", nameIt: "Montenegro", nameNl: "Montenegro", flag: "🇲🇪", phoneCode: "+382", region: "Europe", priority: 3 },
  { code: "MA", nameFr: "Maroc", nameEn: "Morocco", nameEs: "Marruecos", nameDe: "Marokko", namePt: "Marrocos", nameZh: "摩洛哥", nameAr: "المغرب", nameRu: "Марокко", nameIt: "Marocco", nameNl: "Marokko", flag: "🇲🇦", phoneCode: "+212", region: "Africa", priority: 3 },
  { code: "MZ", nameFr: "Mozambique", nameEn: "Mozambique", nameEs: "Mozambique", nameDe: "Mosambik", namePt: "Moçambique", nameZh: "莫桑比克", nameAr: "موزمبيق", nameRu: "Мозамбик", nameIt: "Mozambico", nameNl: "Mozambique", flag: "🇲🇿", phoneCode: "+258", region: "Africa", priority: 3 },
  { code: "MM", nameFr: "Myanmar", nameEn: "Myanmar", nameEs: "Birmania", nameDe: "Myanmar", namePt: "Myanmar", nameZh: "缅甸", nameAr: "ميانمار", nameRu: "Мьянма", nameIt: "Myanmar", nameNl: "Myanmar", flag: "🇲🇲", phoneCode: "+95", region: "Asia", priority: 3 },
  { code: "NA", nameFr: "Namibie", nameEn: "Namibia", nameEs: "Namibia", nameDe: "Namibia", namePt: "Namíbia", nameZh: "纳米比亚", nameAr: "ناميبيا", nameRu: "Намибия", nameIt: "Namibia", nameNl: "Namibië", flag: "🇳🇦", phoneCode: "+264", region: "Africa", priority: 3 },
  { code: "NR", nameFr: "Nauru", nameEn: "Nauru", nameEs: "Nauru", nameDe: "Nauru", namePt: "Nauru", nameZh: "瑙鲁", nameAr: "ناورو", nameRu: "Науру", nameIt: "Nauru", nameNl: "Nauru", flag: "🇳🇷", phoneCode: "+674", region: "Oceania", priority: 3 },
  { code: "NP", nameFr: "Népal", nameEn: "Nepal", nameEs: "Nepal", nameDe: "Nepal", namePt: "Nepal", nameZh: "尼泊尔", nameAr: "نيبال", nameRu: "Непал", nameIt: "Nepal", nameNl: "Nepal", flag: "🇳🇵", phoneCode: "+977", region: "Asia", priority: 3 },
  { code: "NL", nameFr: "Pays-Bas", nameEn: "Netherlands", nameEs: "Países Bajos", nameDe: "Niederlande", namePt: "Países Baixos", nameZh: "荷兰", nameAr: "هولندا", nameRu: "Нидерланды", nameIt: "Paesi Bassi", nameNl: "Nederland", flag: "🇳🇱", phoneCode: "+31", region: "Europe", priority: 3 },
  { code: "NZ", nameFr: "Nouvelle-Zélande", nameEn: "New Zealand", nameEs: "Nueva Zelanda", nameDe: "Neuseeland", namePt: "Nova Zelândia", nameZh: "新西兰", nameAr: "نيوزيلندا", nameRu: "Новая Зеландия", nameIt: "Nuova Zelanda", nameNl: "Nieuw-Zeeland", flag: "🇳🇿", phoneCode: "+64", region: "Oceania", priority: 3 },
  { code: "NI", nameFr: "Nicaragua", nameEn: "Nicaragua", nameEs: "Nicaragua", nameDe: "Nicaragua", namePt: "Nicarágua", nameZh: "尼加拉瓜", nameAr: "نيكاراغوا", nameRu: "Никарагуа", nameIt: "Nicaragua", nameNl: "Nicaragua", flag: "🇳🇮", phoneCode: "+505", region: "Central America", priority: 3 },
  { code: "NE", nameFr: "Niger", nameEn: "Niger", nameEs: "Níger", nameDe: "Niger", namePt: "Níger", nameZh: "尼日尔", nameAr: "النيجر", nameRu: "Нигер", nameIt: "Niger", nameNl: "Niger", flag: "🇳🇪", phoneCode: "+227", region: "Africa", priority: 3 },
  { code: "NG", nameFr: "Nigéria", nameEn: "Nigeria", nameEs: "Nigeria", nameDe: "Nigeria", namePt: "Nigéria", nameZh: "尼日利亚", nameAr: "نيجيريا", nameRu: "Нигерия", nameIt: "Nigeria", nameNl: "Nigeria", flag: "🇳🇬", phoneCode: "+234", region: "Africa", priority: 3 },
  { code: "MK", nameFr: "Macédoine du Nord", nameEn: "North Macedonia", nameEs: "Macedonia del Norte", nameDe: "Nordmazedonien", namePt: "Macedônia do Norte", nameZh: "北马其顿", nameAr: "مقدونيا الشمالية", nameRu: "Северная Македония", nameIt: "Macedonia del Nord", nameNl: "Noord-Macedonië", flag: "🇲🇰", phoneCode: "+389", region: "Europe", priority: 3 },
  { code: "NO", nameFr: "Norvège", nameEn: "Norway", nameEs: "Noruega", nameDe: "Norwegen", namePt: "Noruega", nameZh: "挪威", nameAr: "النرويج", nameRu: "Норвегия", nameIt: "Norvegia", nameNl: "Noorwegen", flag: "🇳🇴", phoneCode: "+47", region: "Europe", priority: 3 },
  { code: "OM", nameFr: "Oman", nameEn: "Oman", nameEs: "Omán", nameDe: "Oman", namePt: "Omã", nameZh: "阿曼", nameAr: "عُمان", nameRu: "Оман", nameIt: "Oman", nameNl: "Oman", flag: "🇴🇲", phoneCode: "+968", region: "Asia", priority: 3 },
  { code: "PK", nameFr: "Pakistan", nameEn: "Pakistan", nameEs: "Pakistán", nameDe: "Pakistan", namePt: "Paquistão", nameZh: "巴基斯坦", nameAr: "باكستان", nameRu: "Пакистан", nameIt: "Pakistan", nameNl: "Pakistan", flag: "🇵🇰", phoneCode: "+92", region: "Asia", priority: 3 },
  { code: "PW", nameFr: "Palaos", nameEn: "Palau", nameEs: "Palaos", nameDe: "Palau", namePt: "Palau", nameZh: "帕劳", nameAr: "بالاو", nameRu: "Палау", nameIt: "Palau", nameNl: "Palau", flag: "🇵🇼", phoneCode: "+680", region: "Oceania", priority: 3 },
  { code: "PS", nameFr: "Palestine", nameEn: "Palestine", nameEs: "Palestina", nameDe: "Palästina", namePt: "Palestina", nameZh: "巴勒斯坦", nameAr: "فلسطين", nameRu: "Палестина", nameIt: "Palestina", nameNl: "Palestina", flag: "🇵🇸", phoneCode: "+970", region: "Asia", priority: 3 },
  { code: "PA", nameFr: "Panama", nameEn: "Panama", nameEs: "Panamá", nameDe: "Panama", namePt: "Panamá", nameZh: "巴拿马", nameAr: "بنما", nameRu: "Панама", nameIt: "Panama", nameNl: "Panama", flag: "🇵🇦", phoneCode: "+507", region: "Central America", priority: 3 },
  { code: "PG", nameFr: "Papouasie-Nouvelle-Guinée", nameEn: "Papua New Guinea", nameEs: "Papúa Nueva Guinea", nameDe: "Papua-Neuguinea", namePt: "Papua-Nova Guiné", nameZh: "巴布亚新几内亚", nameAr: "بابوا غينيا الجديدة", nameRu: "Папуа-Новая Гвинея", nameIt: "Papua Nuova Guinea", nameNl: "Papoea-Nieuw-Guinea", flag: "🇵🇬", phoneCode: "+675", region: "Oceania", priority: 3 },
  { code: "PY", nameFr: "Paraguay", nameEn: "Paraguay", nameEs: "Paraguay", nameDe: "Paraguay", namePt: "Paraguai", nameZh: "巴拉圭", nameAr: "باراغواي", nameRu: "Парагвай", nameIt: "Paraguay", nameNl: "Paraguay", flag: "🇵🇾", phoneCode: "+595", region: "South America", priority: 3 },
  { code: "PE", nameFr: "Pérou", nameEn: "Peru", nameEs: "Perú", nameDe: "Peru", namePt: "Peru", nameZh: "秘鲁", nameAr: "بيرو", nameRu: "Перу", nameIt: "Perù", nameNl: "Peru", flag: "🇵🇪", phoneCode: "+51", region: "South America", priority: 3 },
  { code: "PH", nameFr: "Philippines", nameEn: "Philippines", nameEs: "Filipinas", nameDe: "Philippinen", namePt: "Filipinas", nameZh: "菲律宾", nameAr: "الفلبين", nameRu: "Филиппины", nameIt: "Filippine", nameNl: "Filipijnen", flag: "🇵🇭", phoneCode: "+63", region: "Asia", priority: 3 },
  { code: "PL", nameFr: "Pologne", nameEn: "Poland", nameEs: "Polonia", nameDe: "Polen", namePt: "Polônia", nameZh: "波兰", nameAr: "بولندا", nameRu: "Польша", nameIt: "Polonia", nameNl: "Polen", flag: "🇵🇱", phoneCode: "+48", region: "Europe", priority: 3 },
  { code: "PT", nameFr: "Portugal", nameEn: "Portugal", nameEs: "Portugal", nameDe: "Portugal", namePt: "Portugal", nameZh: "葡萄牙", nameAr: "البرتغال", nameRu: "Португалия", nameIt: "Portogallo", nameNl: "Portugal", flag: "🇵🇹", phoneCode: "+351", region: "Europe", priority: 3 },
  { code: "QA", nameFr: "Qatar", nameEn: "Qatar", nameEs: "Catar", nameDe: "Katar", namePt: "Catar", nameZh: "卡塔尔", nameAr: "قطر", nameRu: "Катар", nameIt: "Qatar", nameNl: "Qatar", flag: "🇶🇦", phoneCode: "+974", region: "Asia", priority: 3 },
  { code: "RO", nameFr: "Roumanie", nameEn: "Romania", nameEs: "Rumania", nameDe: "Rumänien", namePt: "Romênia", nameZh: "罗马尼亚", nameAr: "رومانيا", nameRu: "Румыния", nameIt: "Romania", nameNl: "Roemenië", flag: "🇷🇴", phoneCode: "+40", region: "Europe", priority: 3 },
  { code: "RW", nameFr: "Rwanda", nameEn: "Rwanda", nameEs: "Ruanda", nameDe: "Ruanda", namePt: "Ruanda", nameZh: "卢旺达", nameAr: "رواندا", nameRu: "Руанда", nameIt: "Ruanda", nameNl: "Rwanda", flag: "🇷🇼", phoneCode: "+250", region: "Africa", priority: 3 },
  { code: "KN", nameFr: "Saint-Christophe-et-Niévès", nameEn: "Saint Kitts and Nevis", nameEs: "San Cristóbal y Nieves", nameDe: "St. Kitts und Nevis", namePt: "São Cristóvão e Névis", nameZh: "圣基茨和尼维斯", nameAr: "سانت كيتس ونيفيس", nameRu: "Сент-Китс и Невис", nameIt: "Saint Kitts e Nevis", nameNl: "Saint Kitts en Nevis", flag: "🇰🇳", phoneCode: "+1869", region: "Caribbean", priority: 3 },
  { code: "LC", nameFr: "Sainte-Lucie", nameEn: "Saint Lucia", nameEs: "Santa Lucía", nameDe: "St. Lucia", namePt: "Santa Lúcia", nameZh: "圣卢西亚", nameAr: "سانت لوسيا", nameRu: "Сент-Люсия", nameIt: "Saint Lucia", nameNl: "Saint Lucia", flag: "🇱🇨", phoneCode: "+1758", region: "Caribbean", priority: 3 },
  { code: "VC", nameFr: "Saint-Vincent-et-les-Grenadines", nameEn: "Saint Vincent and the Grenadines", nameEs: "San Vicente y las Granadinas", nameDe: "St. Vincent und die Grenadinen", namePt: "São Vicente e Granadinas", nameZh: "圣文森特和格林纳丁斯", nameAr: "سانت فنسنت وجزر غرينادين", nameRu: "Сент-Винсент и Гренадины", nameIt: "Saint Vincent e Grenadine", nameNl: "Saint Vincent en de Grenadines", flag: "🇻🇨", phoneCode: "+1784", region: "Caribbean", priority: 3 },
  { code: "WS", nameFr: "Samoa", nameEn: "Samoa", nameEs: "Samoa", nameDe: "Samoa", namePt: "Samoa", nameZh: "萨摩亚", nameAr: "ساموا", nameRu: "Самоа", nameIt: "Samoa", nameNl: "Samoa", flag: "🇼🇸", phoneCode: "+685", region: "Oceania", priority: 3 },
  { code: "SM", nameFr: "Saint-Marin", nameEn: "San Marino", nameEs: "San Marino", nameDe: "San Marino", namePt: "San Marino", nameZh: "圣马力诺", nameAr: "سان مارينو", nameRu: "Сан-Марино", nameIt: "San Marino", nameNl: "San Marino", flag: "🇸🇲", phoneCode: "+378", region: "Europe", priority: 3 },
  { code: "ST", nameFr: "Sao Tomé-et-Principe", nameEn: "Sao Tome and Principe", nameEs: "Santo Tomé y Príncipe", nameDe: "São Tomé und Príncipe", namePt: "São Tomé e Príncipe", nameZh: "圣多美和普林西比", nameAr: "ساو تومي وبرينسيبي", nameRu: "Сан-Томе и Принсипи", nameIt: "São Tomé e Príncipe", nameNl: "Sao Tomé en Principe", flag: "🇸🇹", phoneCode: "+239", region: "Africa", priority: 3 },
  { code: "SA", nameFr: "Arabie saoudite", nameEn: "Saudi Arabia", nameEs: "Arabia Saudí", nameDe: "Saudi-Arabien", namePt: "Arábia Saudita", nameZh: "沙特阿拉伯", nameAr: "المملكة العربية السعودية", nameRu: "Саудовская Аравия", nameIt: "Arabia Saudita", nameNl: "Saoedi-Arabië", flag: "🇸🇦", phoneCode: "+966", region: "Asia", priority: 3 },
  { code: "SN", nameFr: "Sénégal", nameEn: "Senegal", nameEs: "Senegal", nameDe: "Senegal", namePt: "Senegal", nameZh: "塞内加尔", nameAr: "السنغال", nameRu: "Сенегал", nameIt: "Senegal", nameNl: "Senegal", flag: "🇸🇳", phoneCode: "+221", region: "Africa", priority: 3 },
  { code: "RS", nameFr: "Serbie", nameEn: "Serbia", nameEs: "Serbia", nameDe: "Serbien", namePt: "Sérvia", nameZh: "塞尔维亚", nameAr: "صربيا", nameRu: "Сербия", nameIt: "Serbia", nameNl: "Servië", flag: "🇷🇸", phoneCode: "+381", region: "Europe", priority: 3 },
  { code: "SC", nameFr: "Seychelles", nameEn: "Seychelles", nameEs: "Seychelles", nameDe: "Seychellen", namePt: "Seychelles", nameZh: "塞舌尔", nameAr: "سيشيل", nameRu: "Сейшелы", nameIt: "Seychelles", nameNl: "Seychellen", flag: "🇸🇨", phoneCode: "+248", region: "Africa", priority: 3 },
  { code: "SL", nameFr: "Sierra Leone", nameEn: "Sierra Leone", nameEs: "Sierra Leona", nameDe: "Sierra Leone", namePt: "Serra Leoa", nameZh: "塞拉利昂", nameAr: "سيراليون", nameRu: "Сьерра-Леоне", nameIt: "Sierra Leone", nameNl: "Sierra Leone", flag: "🇸🇱", phoneCode: "+232", region: "Africa", priority: 3 },
  { code: "SG", nameFr: "Singapour", nameEn: "Singapore", nameEs: "Singapur", nameDe: "Singapur", namePt: "Singapura", nameZh: "新加坡", nameAr: "سنغافورة", nameRu: "Сингапур", nameIt: "Singapore", nameNl: "Singapore", flag: "🇸🇬", phoneCode: "+65", region: "Asia", priority: 3 },
  { code: "SK", nameFr: "Slovaquie", nameEn: "Slovakia", nameEs: "Eslovaquia", nameDe: "Slowakei", namePt: "Eslováquia", nameZh: "斯洛伐克", nameAr: "سلوفاكيا", nameRu: "Словакия", nameIt: "Slovacchia", nameNl: "Slowakije", flag: "🇸🇰", phoneCode: "+421", region: "Europe", priority: 3 },
  { code: "SI", nameFr: "Slovénie", nameEn: "Slovenia", nameEs: "Eslovenia", nameDe: "Slowenien", namePt: "Eslovênia", nameZh: "斯洛文尼亚", nameAr: "سلوفينيا", nameRu: "Словения", nameIt: "Slovenia", nameNl: "Slovenië", flag: "🇸🇮", phoneCode: "+386", region: "Europe", priority: 3 },
  { code: "SB", nameFr: "Îles Salomon", nameEn: "Solomon Islands", nameEs: "Islas Salomón", nameDe: "Salomonen", namePt: "Ilhas Salomão", nameZh: "所罗门群岛", nameAr: "جزر سليمان", nameRu: "Соломоновы Острова", nameIt: "Isole Salomone", nameNl: "Salomonseilanden", flag: "🇸🇧", phoneCode: "+677", region: "Oceania", priority: 3 },
  { code: "SO", nameFr: "Somalie", nameEn: "Somalia", nameEs: "Somalia", nameDe: "Somalia", namePt: "Somália", nameZh: "索马里", nameAr: "الصومال", nameRu: "Сомали", nameIt: "Somalia", nameNl: "Somalië", flag: "🇸🇴", phoneCode: "+252", region: "Africa", priority: 3 },
  { code: "ZA", nameFr: "Afrique du Sud", nameEn: "South Africa", nameEs: "Sudáfrica", nameDe: "Südafrika", namePt: "África do Sul", nameZh: "南非", nameAr: "جنوب أفريقيا", nameRu: "Южная Африка", nameIt: "Sudafrica", nameNl: "Zuid-Afrika", flag: "🇿🇦", phoneCode: "+27", region: "Africa", priority: 3 },
  { code: "SS", nameFr: "Soudan du Sud", nameEn: "South Sudan", nameEs: "Sudán del Sur", nameDe: "Südsudan", namePt: "Sudão do Sul", nameZh: "南苏丹", nameAr: "جنوب السودان", nameRu: "Южный Судан", nameIt: "Sudan del Sud", nameNl: "Zuid-Soedan", flag: "🇸🇸", phoneCode: "+211", region: "Africa", priority: 3 },
  { code: "LK", nameFr: "Sri Lanka", nameEn: "Sri Lanka", nameEs: "Sri Lanka", nameDe: "Sri Lanka", namePt: "Sri Lanka", nameZh: "斯里兰卡", nameAr: "سريلانكا", nameRu: "Шри-Ланка", nameIt: "Sri Lanka", nameNl: "Sri Lanka", flag: "🇱🇰", phoneCode: "+94", region: "Asia", priority: 3 },
  { code: "SD", nameFr: "Soudan", nameEn: "Sudan", nameEs: "Sudán", nameDe: "Sudan", namePt: "Sudão", nameZh: "苏丹", nameAr: "السودان", nameRu: "Судан", nameIt: "Sudan", nameNl: "Soedan", flag: "🇸🇩", phoneCode: "+249", region: "Africa", priority: 3 },
  { code: "SR", nameFr: "Suriname", nameEn: "Suriname", nameEs: "Surinam", nameDe: "Suriname", namePt: "Suriname", nameZh: "苏里南", nameAr: "سورينام", nameRu: "Суринам", nameIt: "Suriname", nameNl: "Suriname", flag: "🇸🇷", phoneCode: "+597", region: "South America", priority: 3 },
  { code: "SE", nameFr: "Suède", nameEn: "Sweden", nameEs: "Suecia", nameDe: "Schweden", namePt: "Suécia", nameZh: "瑞典", nameAr: "السويد", nameRu: "Швеция", nameIt: "Svezia", nameNl: "Zweden", flag: "🇸🇪", phoneCode: "+46", region: "Europe", priority: 3 },
  { code: "CH", nameFr: "Suisse", nameEn: "Switzerland", nameEs: "Suiza", nameDe: "Schweiz", namePt: "Suíça", nameZh: "瑞士", nameAr: "سويسرا", nameRu: "Швейцария", nameIt: "Svizzera", nameNl: "Zwitserland", flag: "🇨🇭", phoneCode: "+41", region: "Europe", priority: 3 },
  { code: "SY", nameFr: "Syrie", nameEn: "Syria", nameEs: "Siria", nameDe: "Syrien", namePt: "Síria", nameZh: "叙利亚", nameAr: "سوريا", nameRu: "Сирия", nameIt: "Siria", nameNl: "Syrië", flag: "🇸🇾", phoneCode: "+963", region: "Asia", priority: 3 },
  { code: "TW", nameFr: "Taïwan", nameEn: "Taiwan", nameEs: "Taiwán", nameDe: "Taiwan", namePt: "Taiwan", nameZh: "台湾", nameAr: "تايوان", nameRu: "Тайвань", nameIt: "Taiwan", nameNl: "Taiwan", flag: "🇹🇼", phoneCode: "+886", region: "Asia", priority: 3 },
  { code: "TJ", nameFr: "Tadjikistan", nameEn: "Tajikistan", nameEs: "Tayikistán", nameDe: "Tadschikistan", namePt: "Tajiquistão", nameZh: "塔吉克斯坦", nameAr: "طاجيكستان", nameRu: "Таджикистан", nameIt: "Tagikistan", nameNl: "Tadzjikistan", flag: "🇹🇯", phoneCode: "+992", region: "Asia", priority: 3 },
  { code: "TZ", nameFr: "Tanzanie", nameEn: "Tanzania", nameEs: "Tanzania", nameDe: "Tansania", namePt: "Tanzânia", nameZh: "坦桑尼亚", nameAr: "تنزانيا", nameRu: "Танзания", nameIt: "Tanzania", nameNl: "Tanzania", flag: "🇹🇿", phoneCode: "+255", region: "Africa", priority: 3 },
  { code: "TH", nameFr: "Thaïlande", nameEn: "Thailand", nameEs: "Tailandia", nameDe: "Thailand", namePt: "Tailândia", nameZh: "泰国", nameAr: "تايلاند", nameRu: "Таиланд", nameIt: "Tailandia", nameNl: "Thailand", flag: "🇹🇭", phoneCode: "+66", region: "Asia", priority: 3 },
  { code: "TL", nameFr: "Timor oriental", nameEn: "Timor-Leste", nameEs: "Timor Oriental", nameDe: "Osttimor", namePt: "Timor-Leste", nameZh: "东帝汶", nameAr: "تيمور الشرقية", nameRu: "Восточный Тимор", nameIt: "Timor Est", nameNl: "Oost-Timor", flag: "🇹🇱", phoneCode: "+670", region: "Asia", priority: 3 },
  { code: "TG", nameFr: "Togo", nameEn: "Togo", nameEs: "Togo", nameDe: "Togo", namePt: "Togo", nameZh: "多哥", nameAr: "توغو", nameRu: "Того", nameIt: "Togo", nameNl: "Togo", flag: "🇹🇬", phoneCode: "+228", region: "Africa", priority: 3 },
  { code: "TO", nameFr: "Tonga", nameEn: "Tonga", nameEs: "Tonga", nameDe: "Tonga", namePt: "Tonga", nameZh: "汤加", nameAr: "تونغا", nameRu: "Тонга", nameIt: "Tonga", nameNl: "Tonga", flag: "🇹🇴", phoneCode: "+676", region: "Oceania", priority: 3 },
  { code: "TT", nameFr: "Trinité-et-Tobago", nameEn: "Trinidad and Tobago", nameEs: "Trinidad y Tobago", nameDe: "Trinidad und Tobago", namePt: "Trinidad e Tobago", nameZh: "特立尼达和多巴哥", nameAr: "ترينيداد وتوباغو", nameRu: "Тринидад и Тобаго", nameIt: "Trinidad e Tobago", nameNl: "Trinidad en Tobago", flag: "🇹🇹", phoneCode: "+1868", region: "Caribbean", priority: 3 },
  { code: "TN", nameFr: "Tunisie", nameEn: "Tunisia", nameEs: "Túnez", nameDe: "Tunesien", namePt: "Tunísia", nameZh: "突尼斯", nameAr: "تونس", nameRu: "Тунис", nameIt: "Tunisia", nameNl: "Tunesië", flag: "🇹🇳", phoneCode: "+216", region: "Africa", priority: 3 },
  { code: "TR", nameFr: "Turquie", nameEn: "Turkey", nameEs: "Turquía", nameDe: "Türkei", namePt: "Turquia", nameZh: "土耳其", nameAr: "تركيا", nameRu: "Турция", nameIt: "Turchia", nameNl: "Turkije", flag: "🇹🇷", phoneCode: "+90", region: "Asia", priority: 3 },
  { code: "TM", nameFr: "Turkménistan", nameEn: "Turkmenistan", nameEs: "Turkmenistán", nameDe: "Turkmenistan", namePt: "Turcomenistão", nameZh: "土库曼斯坦", nameAr: "تركمانستان", nameRu: "Туркменистан", nameIt: "Turkmenistan", nameNl: "Turkmenistan", flag: "🇹🇲", phoneCode: "+993", region: "Asia", priority: 3 },
  { code: "TV", nameFr: "Tuvalu", nameEn: "Tuvalu", nameEs: "Tuvalu", nameDe: "Tuvalu", namePt: "Tuvalu", nameZh: "图瓦卢", nameAr: "توفالو", nameRu: "Тувалу", nameIt: "Tuvalu", nameNl: "Tuvalu", flag: "🇹🇻", phoneCode: "+688", region: "Oceania", priority: 3 },
  { code: "UG", nameFr: "Ouganda", nameEn: "Uganda", nameEs: "Uganda", nameDe: "Uganda", namePt: "Uganda", nameZh: "乌干达", nameAr: "أوغندا", nameRu: "Уганда", nameIt: "Uganda", nameNl: "Oeganda", flag: "🇺🇬", phoneCode: "+256", region: "Africa", priority: 3 },
  { code: "UA", nameFr: "Ukraine", nameEn: "Ukraine", nameEs: "Ucrania", nameDe: "Ukraine", namePt: "Ucrânia", nameZh: "乌克兰", nameAr: "أوكرانيا", nameRu: "Украина", nameIt: "Ucraina", nameNl: "Oekraïne", flag: "🇺🇦", phoneCode: "+380", region: "Europe", priority: 3 },
  { code: "AE", nameFr: "Émirats arabes unis", nameEn: "United Arab Emirates", nameEs: "Emiratos Árabes Unidos", nameDe: "Vereinigte Arabische Emirate", namePt: "Emirados Árabes Unidos", nameZh: "阿拉伯联合酋长国", nameAr: "الإمارات العربية المتحدة", nameRu: "Объединённые Арабские Эмираты", nameIt: "Emirati Arabi Uniti", nameNl: "Verenigde Arabische Emiraten", flag: "🇦🇪", phoneCode: "+971", region: "Asia", priority: 3 },
  { code: "US", nameFr: "États-Unis", nameEn: "United States", nameEs: "Estados Unidos", nameDe: "Vereinigte Staaten", namePt: "Estados Unidos", nameZh: "美国", nameAr: "الولايات المتحدة", nameRu: "Соединённые Штаты", nameIt: "Stati Uniti", nameNl: "Verenigde Staten", flag: "🇺🇸", phoneCode: "+1", region: "North America", priority: 3 },
  { code: "UY", nameFr: "Uruguay", nameEn: "Uruguay", nameEs: "Uruguay", nameDe: "Uruguay", namePt: "Uruguai", nameZh: "乌拉圭", nameAr: "أوروغواي", nameRu: "Уругвай", nameIt: "Uruguay", nameNl: "Uruguay", flag: "🇺🇾", phoneCode: "+598", region: "South America", priority: 3 },
  { code: "UZ", nameFr: "Ouzbékistan", nameEn: "Uzbekistan", nameEs: "Uzbekistán", nameDe: "Usbekistan", namePt: "Uzbequistão", nameZh: "乌兹别克斯坦", nameAr: "أوزبكستان", nameRu: "Узбекистан", nameIt: "Uzbekistan", nameNl: "Oezbekistan", flag: "🇺🇿", phoneCode: "+998", region: "Asia", priority: 3 },
  { code: "VU", nameFr: "Vanuatu", nameEn: "Vanuatu", nameEs: "Vanuatu", nameDe: "Vanuatu", namePt: "Vanuatu", nameZh: "瓦努阿图", nameAr: "فانواتو", nameRu: "Вануату", nameIt: "Vanuatu", nameNl: "Vanuatu", flag: "🇻🇺", phoneCode: "+678", region: "Oceania", priority: 3 },
  { code: "VA", nameFr: "Vatican", nameEn: "Vatican City", nameEs: "Ciudad del Vaticano", nameDe: "Vatikanstadt", namePt: "Cidade do Vaticano", nameZh: "梵蒂冈", nameAr: "الفاتيكان", nameRu: "Ватикан", nameIt: "Città del Vaticano", nameNl: "Vaticaanstad", flag: "🇻🇦", phoneCode: "+39", region: "Europe", priority: 3 },
  { code: "VE", nameFr: "Venezuela", nameEn: "Venezuela", nameEs: "Venezuela", nameDe: "Venezuela", namePt: "Venezuela", nameZh: "委内瑞拉", nameAr: "فنزويلا", nameRu: "Венесуэла", nameIt: "Venezuela", nameNl: "Venezuela", flag: "🇻🇪", phoneCode: "+58", region: "South America", priority: 3 },
  { code: "VN", nameFr: "Vietnam", nameEn: "Vietnam", nameEs: "Vietnam", nameDe: "Vietnam", namePt: "Vietnã", nameZh: "越南", nameAr: "فيتنام", nameRu: "Вьетнам", nameIt: "Vietnam", nameNl: "Vietnam", flag: "🇻🇳", phoneCode: "+84", region: "Asia", priority: 3 },
  { code: "YE", nameFr: "Yémen", nameEn: "Yemen", nameEs: "Yemen", nameDe: "Jemen", namePt: "Iêmen", nameZh: "也门", nameAr: "اليمن", nameRu: "Йемен", nameIt: "Yemen", nameNl: "Jemen", flag: "🇾🇪", phoneCode: "+967", region: "Asia", priority: 3 },
  { code: "ZM", nameFr: "Zambie", nameEn: "Zambia", nameEs: "Zambia", nameDe: "Sambia", namePt: "Zâmbia", nameZh: "赞比亚", nameAr: "زامبيا", nameRu: "Замбия", nameIt: "Zambia", nameNl: "Zambia", flag: "🇿🇲", phoneCode: "+260", region: "Africa", priority: 3 },
  { code: "ZW", nameFr: "Zimbabwe", nameEn: "Zimbabwe", nameEs: "Zimbabue", nameDe: "Simbabwe", namePt: "Zimbábue", nameZh: "津巴布韦", nameAr: "زيمبابوي", nameRu: "Зимбабве", nameIt: "Zimbabwe", nameNl: "Zimbabwe", flag: "🇿🇼", phoneCode: "+263", region: "Africa", priority: 3 },

  // ========================================
  // 🇫🇷 TERRITOIRES FRANÇAIS D'OUTRE-MER
  // ========================================
  { code: "GP", nameFr: "Guadeloupe", nameEn: "Guadeloupe", nameEs: "Guadalupe", nameDe: "Guadeloupe", namePt: "Guadalupe", nameZh: "瓜德罗普", nameAr: "غوادلوب", nameRu: "Гваделупа", nameIt: "Guadalupa", nameNl: "Guadeloupe", flag: "🇬🇵", phoneCode: "+590", region: "Caribbean", priority: 3 },
  { code: "MQ", nameFr: "Martinique", nameEn: "Martinique", nameEs: "Martinica", nameDe: "Martinique", namePt: "Martinica", nameZh: "马提尼克", nameAr: "مارتينيك", nameRu: "Мартиника", nameIt: "Martinica", nameNl: "Martinique", flag: "🇲🇶", phoneCode: "+596", region: "Caribbean", priority: 3 },
  { code: "RE", nameFr: "La Réunion", nameEn: "Réunion", nameEs: "Reunión", nameDe: "Réunion", namePt: "Reunião", nameZh: "留尼汪", nameAr: "ريونيون", nameRu: "Реюньон", nameIt: "Riunione", nameNl: "Réunion", flag: "🇷🇪", phoneCode: "+262", region: "Africa", priority: 3 },
  { code: "YT", nameFr: "Mayotte", nameEn: "Mayotte", nameEs: "Mayotte", nameDe: "Mayotte", namePt: "Mayotte", nameZh: "马约特", nameAr: "مايوت", nameRu: "Майотта", nameIt: "Mayotte", nameNl: "Mayotte", flag: "🇾🇹", phoneCode: "+262", region: "Africa", priority: 3 },
  { code: "NC", nameFr: "Nouvelle-Calédonie", nameEn: "New Caledonia", nameEs: "Nueva Caledonia", nameDe: "Neukaledonien", namePt: "Nova Caledônia", nameZh: "新喀里多尼亚", nameAr: "كاليدونيا الجديدة", nameRu: "Новая Каледония", nameIt: "Nuova Caledonia", nameNl: "Nieuw-Caledonië", flag: "🇳🇨", phoneCode: "+687", region: "Oceania", priority: 3 },
  { code: "PF", nameFr: "Polynésie française", nameEn: "French Polynesia", nameEs: "Polinesia Francesa", nameDe: "Französisch-Polynesien", namePt: "Polinésia Francesa", nameZh: "法属波利尼西亚", nameAr: "بولينيزيا الفرنسية", nameRu: "Французская Полинезия", nameIt: "Polinesia francese", nameNl: "Frans-Polynesië", flag: "🇵🇫", phoneCode: "+689", region: "Oceania", priority: 3 },
  { code: "WF", nameFr: "Wallis-et-Futuna", nameEn: "Wallis and Futuna", nameEs: "Wallis y Futuna", nameDe: "Wallis und Futuna", namePt: "Wallis e Futuna", nameZh: "瓦利斯和富图纳", nameAr: "واليس وفوتونا", nameRu: "Уоллис и Футуна", nameIt: "Wallis e Futuna", nameNl: "Wallis en Futuna", flag: "🇼🇫", phoneCode: "+681", region: "Oceania", priority: 3 },
  { code: "PM", nameFr: "Saint-Pierre-et-Miquelon", nameEn: "Saint Pierre and Miquelon", nameEs: "San Pedro y Miquelón", nameDe: "Saint-Pierre und Miquelon", namePt: "São Pedro e Miquelon", nameZh: "圣皮埃尔和密克隆", nameAr: "سان بيير وميكلون", nameRu: "Сен-Пьер и Микелон", nameIt: "Saint-Pierre e Miquelon", nameNl: "Saint-Pierre en Miquelon", flag: "🇵🇲", phoneCode: "+508", region: "North America", priority: 3 },
  { code: "BL", nameFr: "Saint-Barthélemy", nameEn: "Saint Barthélemy", nameEs: "San Bartolomé", nameDe: "Saint-Barthélemy", namePt: "São Bartolomeu", nameZh: "圣巴泰勒米", nameAr: "سان بارتيليمي", nameRu: "Сен-Бартелеми", nameIt: "Saint-Barthélemy", nameNl: "Saint-Barthélemy", flag: "🇧🇱", phoneCode: "+590", region: "Caribbean", priority: 3 },
  { code: "MF", nameFr: "Saint-Martin", nameEn: "Saint Martin", nameEs: "San Martín", nameDe: "Saint-Martin", namePt: "São Martinho", nameZh: "法属圣马丁", nameAr: "سان مارتن", nameRu: "Сен-Мартен", nameIt: "Saint-Martin", nameNl: "Sint Maarten", flag: "🇲🇫", phoneCode: "+590", region: "Caribbean", priority: 3 },

  // ========================================
  // 🌍 AUTRES TERRITOIRES
  // ========================================
  { code: "PR", nameFr: "Porto Rico", nameEn: "Puerto Rico", nameEs: "Puerto Rico", nameDe: "Puerto Rico", namePt: "Porto Rico", nameZh: "波多黎各", nameAr: "بورتوريكو", nameRu: "Пуэрто-Рико", nameIt: "Porto Rico", nameNl: "Puerto Rico", flag: "🇵🇷", phoneCode: "+1787", region: "Caribbean", priority: 3 },
  { code: "GU", nameFr: "Guam", nameEn: "Guam", nameEs: "Guam", nameDe: "Guam", namePt: "Guam", nameZh: "关岛", nameAr: "غوام", nameRu: "Гуам", nameIt: "Guam", nameNl: "Guam", flag: "🇬🇺", phoneCode: "+1671", region: "Oceania", priority: 3 },
  { code: "VI", nameFr: "Îles Vierges américaines", nameEn: "U.S. Virgin Islands", nameEs: "Islas Vírgenes de EE. UU.", nameDe: "Amerikanische Jungferninseln", namePt: "Ilhas Virgens Americanas", nameZh: "美属维尔京群岛", nameAr: "جزر العذراء الأمريكية", nameRu: "Виргинские острова США", nameIt: "Isole Vergini americane", nameNl: "Amerikaanse Maagdeneilanden", flag: "🇻🇮", phoneCode: "+1340", region: "Caribbean", priority: 3 },
  { code: "AS", nameFr: "Samoa américaines", nameEn: "American Samoa", nameEs: "Samoa Americana", nameDe: "Amerikanisch-Samoa", namePt: "Samoa Americana", nameZh: "美属萨摩亚", nameAr: "ساموا الأمريكية", nameRu: "Американское Самоа", nameIt: "Samoa Americane", nameNl: "Amerikaans-Samoa", flag: "🇦🇸", phoneCode: "+1684", region: "Oceania", priority: 3 },
  { code: "MP", nameFr: "Îles Mariannes du Nord", nameEn: "Northern Mariana Islands", nameEs: "Islas Marianas del Norte", nameDe: "Nördliche Marianen", namePt: "Ilhas Marianas do Norte", nameZh: "北马里亚纳群岛", nameAr: "جزر ماريانا الشمالية", nameRu: "Северные Марианские острова", nameIt: "Isole Marianne Settentrionali", nameNl: "Noordelijke Marianen", flag: "🇲🇵", phoneCode: "+1670", region: "Oceania", priority: 3 },
  { code: "AW", nameFr: "Aruba", nameEn: "Aruba", nameEs: "Aruba", nameDe: "Aruba", namePt: "Aruba", nameZh: "阿鲁巴", nameAr: "أروبا", nameRu: "Аруба", nameIt: "Aruba", nameNl: "Aruba", flag: "🇦🇼", phoneCode: "+297", region: "Caribbean", priority: 3 },
  { code: "CW", nameFr: "Curaçao", nameEn: "Curaçao", nameEs: "Curazao", nameDe: "Curaçao", namePt: "Curaçao", nameZh: "库拉索", nameAr: "كوراساو", nameRu: "Кюрасао", nameIt: "Curaçao", nameNl: "Curaçao", flag: "🇨🇼", phoneCode: "+599", region: "Caribbean", priority: 3 },
  { code: "SX", nameFr: "Sint Maarten", nameEn: "Sint Maarten", nameEs: "Sint Maarten", nameDe: "Sint Maarten", namePt: "Sint Maarten", nameZh: "荷属圣马丁", nameAr: "سينت مارتن", nameRu: "Синт-Мартен", nameIt: "Sint Maarten", nameNl: "Sint Maarten", flag: "🇸🇽", phoneCode: "+1721", region: "Caribbean", priority: 3 },
  { code: "BQ", nameFr: "Pays-Bas caribéens", nameEn: "Caribbean Netherlands", nameEs: "Caribe Neerlandés", nameDe: "Karibische Niederlande", namePt: "Países Baixos Caribenhos", nameZh: "荷兰加勒比区", nameAr: "هولندا الكاريبية", nameRu: "Карибские Нидерланды", nameIt: "Paesi Bassi caraibici", nameNl: "Caribisch Nederland", flag: "🇧🇶", phoneCode: "+599", region: "Caribbean", priority: 3 },
  { code: "GI", nameFr: "Gibraltar", nameEn: "Gibraltar", nameEs: "Gibraltar", nameDe: "Gibraltar", namePt: "Gibraltar", nameZh: "直布罗陀", nameAr: "جبل طارق", nameRu: "Гибралтар", nameIt: "Gibilterra", nameNl: "Gibraltar", flag: "🇬🇮", phoneCode: "+350", region: "Europe", priority: 3 },
  { code: "FO", nameFr: "Îles Féroé", nameEn: "Faroe Islands", nameEs: "Islas Feroe", nameDe: "Färöer", namePt: "Ilhas Faroé", nameZh: "法罗群岛", nameAr: "جزر فارو", nameRu: "Фарерские острова", nameIt: "Isole Fær Øer", nameNl: "Faeröer", flag: "🇫🇴", phoneCode: "+298", region: "Europe", priority: 3 },
  { code: "GL", nameFr: "Groenland", nameEn: "Greenland", nameEs: "Groenlandia", nameDe: "Grönland", namePt: "Groenlândia", nameZh: "格陵兰", nameAr: "غرينلاند", nameRu: "Гренландия", nameIt: "Groenlandia", nameNl: "Groenland", flag: "🇬🇱", phoneCode: "+299", region: "North America", priority: 3 },
  { code: "AX", nameFr: "Îles Åland", nameEn: "Åland Islands", nameEs: "Islas Åland", nameDe: "Åland", namePt: "Ilhas Åland", nameZh: "奥兰群岛", nameAr: "جزر أولاند", nameRu: "Аландские острова", nameIt: "Isole Åland", nameNl: "Åland", flag: "🇦🇽", phoneCode: "+358", region: "Europe", priority: 3 },
  { code: "JE", nameFr: "Jersey", nameEn: "Jersey", nameEs: "Jersey", nameDe: "Jersey", namePt: "Jersey", nameZh: "泽西岛", nameAr: "جيرزي", nameRu: "Джерси", nameIt: "Jersey", nameNl: "Jersey", flag: "🇯🇪", phoneCode: "+44", region: "Europe", priority: 3 },
  { code: "GG", nameFr: "Guernesey", nameEn: "Guernsey", nameEs: "Guernsey", nameDe: "Guernsey", namePt: "Guernsey", nameZh: "根西岛", nameAr: "غيرنزي", nameRu: "Гернси", nameIt: "Guernsey", nameNl: "Guernsey", flag: "🇬🇬", phoneCode: "+44", region: "Europe", priority: 3 },
  { code: "IM", nameFr: "Île de Man", nameEn: "Isle of Man", nameEs: "Isla de Man", nameDe: "Isle of Man", namePt: "Ilha de Man", nameZh: "马恩岛", nameAr: "جزيرة مان", nameRu: "Остров Мэн", nameIt: "Isola di Man", nameNl: "Man", flag: "🇮🇲", phoneCode: "+44", region: "Europe", priority: 3 },
  { code: "BM", nameFr: "Bermudes", nameEn: "Bermuda", nameEs: "Bermudas", nameDe: "Bermuda", namePt: "Bermudas", nameZh: "百慕大", nameAr: "برمودا", nameRu: "Бермудские острова", nameIt: "Bermuda", nameNl: "Bermuda", flag: "🇧🇲", phoneCode: "+1441", region: "North America", priority: 3 },
  { code: "KY", nameFr: "Îles Caïmans", nameEn: "Cayman Islands", nameEs: "Islas Caimán", nameDe: "Kaimaninseln", namePt: "Ilhas Cayman", nameZh: "开曼群岛", nameAr: "جزر كايمان", nameRu: "Каймановы острова", nameIt: "Isole Cayman", nameNl: "Kaaimaneilanden", flag: "🇰🇾", phoneCode: "+1345", region: "Caribbean", priority: 3 },
  { code: "VG", nameFr: "Îles Vierges britanniques", nameEn: "British Virgin Islands", nameEs: "Islas Vírgenes Británicas", nameDe: "Britische Jungferninseln", namePt: "Ilhas Virgens Britânicas", nameZh: "英属维尔京群岛", nameAr: "جزر العذراء البريطانية", nameRu: "Британские Виргинские острова", nameIt: "Isole Vergini britanniche", nameNl: "Britse Maagdeneilanden", flag: "🇻🇬", phoneCode: "+1284", region: "Caribbean", priority: 3 },
  { code: "TC", nameFr: "Îles Turques-et-Caïques", nameEn: "Turks and Caicos Islands", nameEs: "Islas Turcas y Caicos", nameDe: "Turks- und Caicosinseln", namePt: "Ilhas Turcas e Caicos", nameZh: "特克斯和凯科斯群岛", nameAr: "جزر تركس وكايكوس", nameRu: "Тёркс и Кайкос", nameIt: "Isole Turks e Caicos", nameNl: "Turks- en Caicoseilanden", flag: "🇹🇨", phoneCode: "+1649", region: "Caribbean", priority: 3 },
  { code: "AI", nameFr: "Anguilla", nameEn: "Anguilla", nameEs: "Anguila", nameDe: "Anguilla", namePt: "Anguilla", nameZh: "安圭拉", nameAr: "أنغويلا", nameRu: "Ангилья", nameIt: "Anguilla", nameNl: "Anguilla", flag: "🇦🇮", phoneCode: "+1264", region: "Caribbean", priority: 3 },
  { code: "MS", nameFr: "Montserrat", nameEn: "Montserrat", nameEs: "Montserrat", nameDe: "Montserrat", namePt: "Montserrat", nameZh: "蒙特塞拉特", nameAr: "مونتسرات", nameRu: "Монтсеррат", nameIt: "Montserrat", nameNl: "Montserrat", flag: "🇲🇸", phoneCode: "+1664", region: "Caribbean", priority: 3 },
  { code: "FK", nameFr: "Îles Malouines", nameEn: "Falkland Islands", nameEs: "Islas Malvinas", nameDe: "Falklandinseln", namePt: "Ilhas Malvinas", nameZh: "福克兰群岛", nameAr: "جزر فوكلاند", nameRu: "Фолклендские острова", nameIt: "Isole Falkland", nameNl: "Falklandeilanden", flag: "🇫🇰", phoneCode: "+500", region: "South America", priority: 3 },
  { code: "SH", nameFr: "Sainte-Hélène", nameEn: "Saint Helena", nameEs: "Santa Elena", nameDe: "St. Helena", namePt: "Santa Helena", nameZh: "圣赫勒拿", nameAr: "سانت هيلينا", nameRu: "Остров Святой Елены", nameIt: "Sant'Elena", nameNl: "Sint-Helena", flag: "🇸🇭", phoneCode: "+290", region: "Africa", priority: 3 },
  { code: "PN", nameFr: "Îles Pitcairn", nameEn: "Pitcairn Islands", nameEs: "Islas Pitcairn", nameDe: "Pitcairninseln", namePt: "Ilhas Pitcairn", nameZh: "皮特凯恩群岛", nameAr: "جزر بيتكيرن", nameRu: "Острова Питкэрн", nameIt: "Isole Pitcairn", nameNl: "Pitcairneilanden", flag: "🇵🇳", phoneCode: "+64", region: "Oceania", priority: 3 }
];

// ========================================
// 🛠️ FONCTIONS UTILITAIRES
// ========================================

/**
 * Obtenir la liste des pays triée par priorité puis par nom
 */
export const getSortedCountries = (
  language: keyof Omit<CountryData, 'code' | 'flag' | 'phoneCode' | 'region' | 'priority' | 'disabled'> = 'nameEn'
): CountryData[] => {
  return countriesData
    .filter(c => !c.disabled) // masque les entrées non-pays si jamais il en reste
    .slice()                   // évite de muter countriesData
    .sort((a, b) =>
      (a.priority ?? 999) - (b.priority ?? 999) ||
      a[language].localeCompare(b[language])
    );
};


/**
 * Rechercher des pays par nom dans une langue donnée
 */
export const searchCountries = (
  query: string, 
  language: keyof Omit<CountryData, 'code' | 'flag' | 'phoneCode' | 'region' | 'priority' | 'disabled'> = 'nameEn'
): CountryData[] => {
  const normalizedQuery = query.toLowerCase().trim();
  
  if (!normalizedQuery) return getSortedCountries(language);
  
  return countriesData
    .filter(country => 
      !country.disabled && 
      country[language].toLowerCase().includes(normalizedQuery)
    )
    .sort((a, b) => {
      // Priorité d'abord
      if (a.priority !== b.priority) {
        return (a.priority || 999) - (b.priority || 999);
      }
      // Puis tri alphabétique
      return a[language].localeCompare(b[language]);
    });
};

/**
 * Obtenir un pays par son code ISO
 */
export const getCountryByCode = (code: string): CountryData | undefined => {
  return countriesData.find(country => country.code === code && !country.disabled);
};

/**
 * Obtenir les pays par région
 */
export const getCountriesByRegion = (region: string): CountryData[] => {
  return countriesData
    .filter(country => country.region === region && !country.disabled)
    .sort((a, b) => a.nameEn.localeCompare(b.nameEn));
};

/**
 * Obtenir toutes les régions uniques
 */
export const getRegions = (): string[] => {
  const regions = [...new Set(countriesData.map(country => country.region))];
  return regions.filter(region => region !== "").sort();
};

// ========================================
// 🌐 TYPES POUR TYPESCRIPT
// ========================================

export type CountryCode = Exclude<typeof countriesData[number]['code'], 'SEPARATOR'>;
export type Region = typeof countriesData[number]['region'];
export type LanguageKey = keyof Omit<CountryData, 'code' | 'flag' | 'phoneCode' | 'region' | 'priority' | 'disabled'>;

// ========================================
// 📊 STATISTIQUES
// ========================================

export const COUNTRIES_STATS = {
  total: countriesData.filter(c => !c.disabled).length,
  priority: countriesData.filter(c => c.priority === 1).length,
  regions: getRegions().length,
  languages: 10
} as const;

// ========================================
// 🌐 LOCALE-AWARE HELPERS (9 app languages)
// ========================================

/** Sentinel value for the "Other country" option (language-neutral) */
export const OTHER_COUNTRY = 'OTHER';

/** Map app locale → CountryData name property. Hindi has no data → fallback English. */
const LOCALE_TO_NAME_KEY: Record<string, keyof CountryData> = {
  fr: 'nameFr',
  en: 'nameEn',
  es: 'nameEs',
  de: 'nameDe',
  pt: 'namePt',
  ch: 'nameZh',
  ar: 'nameAr',
  ru: 'nameRu',
  hi: 'nameEn',
};

/** Get country name in the given app locale (falls back to English) */
export function getCountryNameByLocale(country: CountryData, locale: string): string {
  const key = LOCALE_TO_NAME_KEY[locale] || 'nameEn';
  return (country[key] as string) || country.nameEn;
}

/** Build a sorted { code, label } list for a <select> in the given locale */
export function getCountriesForLocale(locale: string): { code: string; label: string }[] {
  return countriesData
    .filter((c) => c.code !== 'SEPARATOR')
    .map((c) => ({ code: c.code, label: getCountryNameByLocale(c, locale) }))
    .sort((a, b) => a.label.localeCompare(b.label, locale));
}

/** Resolve a country code (e.g. "FR") to a display name in the given locale */
export function resolveCountryName(code: string, locale: string = 'fr'): string {
  if (!code || code === OTHER_COUNTRY) return code;
  const country = countriesData.find((c) => c.code === code);
  if (!country) return code;
  return getCountryNameByLocale(country, locale);
}