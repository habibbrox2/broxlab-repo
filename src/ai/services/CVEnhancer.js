/**
 * CV Enhancement Service
 * AI-powered CV improvements using Node.js AI services
 * 
 * Features:
 * - Text improvement (bullet points, paragraphs)
 * - ATS scoring
 * - Keyword extraction from job descriptions
 * - CV parsing from text
 * - Cover letter generation
 * - Job match scoring
 */

import { aiRouter } from '../AIRouter.js';
import Logger from '../utils/Logger.js';

class CVEnhancer {
    constructor(options = {}) {
        this.defaultProvider = options.defaultProvider || 'auto';
        this.maxRetries = options.maxRetries || 2;
    }

    /**
     * Improve CV text (bullet point, sentence, paragraph)
     */
    async improveText(text, type = 'bullet') {
        Logger.info('Improving CV text', { type, length: text.length });

        const prompts = {
            bullet: {
                system: 'You are a professional CV writer and career coach. Improve CV bullet points to be more impactful, quantified, and ATS-friendly.',
                user: `Improve this bullet point for a CV. Make it:
1. Action-oriented (start with strong action verbs)
2. Quantified (include numbers/metrics where possible)
3. ATS-friendly (use relevant keywords)
4. Concise (under 100 characters if possible)

Original: {text}

Provide only the improved text, no explanations.`
            },
            sentence: {
                system: 'You are a professional CV writer. Improve CV sentences to be clearer and more professional.',
                user: `Improve this sentence for a CV:
Original: {text}

Make it more professional, clear, and impactful. Provide only the improved text.`
            },
            paragraph: {
                system: 'You are a professional CV writer. Improve CV summaries and paragraphs.',
                user: `Improve this paragraph for a CV summary:
Original: {text}

Make it more impactful, well-structured, and professional. Keep the same length approximately. Provide only the improved text.`
            },
            summary: {
                system: 'You are a professional CV writer and career coach. Write compelling professional summaries.',
                user: `Write a professional summary for a CV based on:
Original: {text}

Make it 2-3 sentences, impactful, and highlight key strengths. Provide only the summary.`
            }
        };

        const prompt = prompts[type] || prompts.bullet;

        try {
            const response = await aiRouter.chat({
                messages: [{
                    role: 'user',
                    content: prompt.user.replace('{text}', text)
                }],
                provider: this.defaultProvider,
                system: prompt.system
            });

            const improved = response.content.trim();

            return {
                improved,
                score: this.calculateImprovementScore(text, improved),
                type
            };
        } catch (error) {
            Logger.error('CV text improvement failed', { error: error.message });
            throw error;
        }
    }

    /**
     * Calculate improvement score
     */
    calculateImprovementScore(original, improved) {
        let score = 50;

        // Length improvement
        if (improved.length > original.length) score += 10;

        // Action verbs check
        const actionVerbs = ['led', 'managed', 'developed', 'created', 'implemented', 'achieved', 'reduced', 'increased', 'delivered', 'designed'];
        const hasActionVerb = actionVerbs.some(verb => improved.toLowerCase().startsWith(verb));
        if (hasActionVerb) score += 20;

        // Numbers/metrics check
        if (/\d+%?/.test(improved)) score += 15;

        // Length check (not too long)
        if (improved.length <= 150) score += 5;

        return Math.min(100, score);
    }

    /**
     * Calculate ATS (Applicant Tracking System) score
     */
    async calculateAtsScore(cvData) {
        Logger.info('Calculating ATS score', { cvId: cvData.id });

        const prompt = `You are an ATS (Applicant Tracking System) expert. Analyze this CV and provide a detailed assessment.

CV Data:
${JSON.stringify(cvData, null, 2)}

Provide your analysis in this exact JSON format (no other text):
{
  "score": 0-100,
  "summary": "Brief overall assessment",
  "keywords": {
    "found": ["keyword1", "keyword2"],
    "missing": ["keyword3", "keyword4"],
    "suggested": ["keyword5"]
  },
  "sections": {
    "summary": "present/missing/weak",
    "experience": "present/missing/weak", 
    "education": "present/missing/weak",
    "skills": "present/missing/weak"
  },
  "readability": "excellent/good/needs_work",
  "formatting": "ats_friendly/needs_work",
  "suggestions": [
    {"priority": "high/medium/low", "text": "suggestion text"}
  ]
}`;

        try {
            const response = await aiRouter.chat({
                messages: [{ role: 'user', content: prompt }],
                provider: this.defaultProvider,
                system: 'You are an ATS expert. Analyze CVs for compatibility with automated tracking systems.'
            });

            const result = JSON.parse(response.content);

            return {
                score: result.score || 0,
                summary: result.summary || '',
                keywords: result.keywords || { found: [], missing: [], suggested: [] },
                sections: result.sections || {},
                readability: result.readability || 'unknown',
                formatting: result.formatting || 'unknown',
                suggestions: result.suggestions || []
            };
        } catch (error) {
            Logger.error('ATS scoring failed', { error: error.message });
            // Fallback to local scoring
            return this.localAtsScore(cvData);
        }
    }

    /**
     * Local fallback ATS scoring
     */
    localAtsScore(cvData) {
        let score = 40;
        const suggestions = [];

        // Check summary
        if (cvData.summary && cvData.summary.length > 50) {
            score += 15;
        } else {
            suggestions.push({ priority: 'high', text: 'Add a professional summary (50+ characters)' });
        }

        // Check experience
        if (cvData.experience && cvData.experience.length > 0) {
            score += 20;
            const hasDates = cvData.experience.every(exp => exp.start_date || exp.end_date);
            if (hasDates) score += 5;
        } else {
            suggestions.push({ priority: 'high', text: 'Add work experience' });
        }

        // Check education
        if (cvData.education && cvData.education.length > 0) {
            score += 10;
        } else {
            suggestions.push({ priority: 'medium', text: 'Add education details' });
        }

        // Check skills
        if (cvData.skills && cvData.skills.length > 0) {
            score += 15;
            if (cvData.skills.length > 5) score += 5;
        } else {
            suggestions.push({ priority: 'high', text: 'Add relevant skills' });
        }

        return {
            score: Math.min(100, score),
            summary: `Basic score: ${score}/100`,
            keywords: { found: cvData.skills || [], missing: [], suggested: [] },
            sections: {
                summary: cvData.summary ? 'present' : 'missing',
                experience: cvData.experience ? 'present' : 'missing',
                education: cvData.education ? 'present' : 'missing',
                skills: cvData.skills ? 'present' : 'missing'
            },
            readability: score > 70 ? 'good' : 'needs_work',
            formatting: 'needs_work',
            suggestions
        };
    }

    /**
     * Extract keywords from job description
     */
    async extractKeywords(jobDescription) {
        Logger.info('Extracting keywords from job description');

        const prompt = `Extract important keywords from this job description for CV optimization.

Job Description:
${jobDescription}

Provide keywords in this JSON format:
{
  "keywords": ["keyword1", "keyword2", ...],
  "importance": {"keyword1": 10, "keyword2": 8, ...},
  "required": ["must-have keyword"],
  "preferred": ["nice-to-have keyword"]
}`;

        try {
            const response = await aiRouter.chat({
                messages: [{ role: 'user', content: prompt }],
                provider: this.defaultProvider,
                system: 'You are a job market expert. Extract keywords from job descriptions.'
            });

            return JSON.parse(response.content);
        } catch (error) {
            Logger.error('Keyword extraction failed', { error: error.message });
            return this.localKeywordExtraction(jobDescription);
        }
    }

    /**
     * Local keyword extraction fallback
     */
    localKeywordExtraction(text) {
        const commonSkills = [
            'javascript', 'python', 'java', 'php', 'ruby', 'c++', 'c#', 'golang', 'rust',
            'react', 'angular', 'vue', 'node', 'django', 'laravel', 'spring',
            'mysql', 'postgresql', 'mongodb', 'redis', 'elasticsearch',
            'aws', 'gcp', 'azure', 'docker', 'kubernetes', 'terraform',
            'html', 'css', 'sql', 'rest', 'graphql', 'git'
        ];

        const textLower = text.toLowerCase();
        const found = commonSkills.filter(skill => textLower.includes(skill));

        const importance = {};
        found.forEach(k => importance[k] = 8);

        return {
            keywords: found,
            importance,
            required: [],
            preferred: found.slice(0, 5)
        };
    }

    /**
     * Match CV to job description
     */
    async matchToJob(cvData, jobDescription) {
        Logger.info('Matching CV to job description');

        // Extract keywords from job
        const jobKeywords = await this.extractKeywords(jobDescription);

        // Get ATS analysis
        const atsScore = await this.calculateAtsScore(cvData);

        // Calculate match percentage
        const cvSkills = (cvData.skills || []).map(s => s.toLowerCase());
        const requiredSkills = (jobKeywords.required || []).map(s => s.toLowerCase());
        const preferredSkills = (jobKeywords.preferred || []).map(s => s.toLowerCase());

        const matchedRequired = requiredSkills.filter(s =>
            cvSkills.some(cs => cs.includes(s) || s.includes(cs))
        );
        const matchedPreferred = preferredSkills.filter(s =>
            cvSkills.some(cs => cs.includes(s) || s.includes(cs))
        );

        const matchScore = Math.round(
            (matchedRequired.length / Math.max(requiredSkills.length, 1)) * 60 +
            (matchedPreferred.length / Math.max(preferredSkills.length, 1)) * 40
        );

        return {
            matchScore,
            atsScore: atsScore.score,
            matchedSkills: {
                required: matchedRequired,
                preferred: matchedPreferred
            },
            missingSkills: {
                required: requiredSkills.filter(s => !matchedRequired.includes(s)),
                preferred: preferredSkills.filter(s => !matchedPreferred.includes(s))
            },
            suggestions: [
                ...atsScore.suggestions,
                ...jobKeywords.suggested?.map(s => ({ priority: 'medium', text: `Add skill: ${s}` })) || []
            ]
        };
    }

    /**
     * Generate cover letter
     */
    async generateCoverLetter(cvData, jobDescription) {
        Logger.info('Generating cover letter');

        const prompt = `Write a professional cover letter based on this CV and job description.

CV:
${JSON.stringify(cvData, null, 2)}

Job Description:
${jobDescription}

Write a compelling cover letter that:
1. Addresses the job requirements
2. Highlights relevant experience
3. Shows enthusiasm for the role
4. Is professional but personable

Provide only the cover letter text.`;

        try {
            const response = await aiRouter.chat({
                messages: [{ role: 'user', content: prompt }],
                provider: this.defaultProvider,
                system: 'You are a professional career coach. Write compelling cover letters.'
            });

            return {
                coverLetter: response.content.trim(),
                wordCount: response.content.split(/\s+/).length
            };
        } catch (error) {
            Logger.error('Cover letter generation failed', { error: error.message });
            throw error;
        }
    }

    /**
     * Parse CV from raw text
     */
    async parseCvText(rawText) {
        Logger.info('Parsing CV from text');

        const prompt = `Parse this CV/resume text and extract structured information.

CV Text:
${rawText}

Provide extracted data in this JSON format:
{
  "name": "Full Name",
  "email": "email@example.com",
  "phone": "+1234567890",
  "location": "City, Country",
  "summary": "Professional summary",
  "experience": [
    {
      "company": "Company Name",
      "position": "Job Title",
      "start_date": "YYYY-MM",
      "end_date": "YYYY-MM or Present",
      "description": "Job description"
    }
  ],
  "education": [
    {
      "institution": "University Name",
      "degree": "Degree Name",
      "field": "Field of Study",
      "year": "YYYY"
    }
  ],
  "skills": ["skill1", "skill2", ...],
  "certifications": ["cert1", ...],
  "languages": ["English", "Bengali", ...]
}`;

        try {
            const response = await aiRouter.chat({
                messages: [{ role: 'user', content: prompt }],
                provider: this.defaultProvider,
                system: 'You are a CV parsing expert. Extract structured data from CV text.'
            });

            return JSON.parse(response.content);
        } catch (error) {
            Logger.error('CV parsing failed', { error: error.message });
            return this.localCvParsing(rawText);
        }
    }

    /**
     * Local CV parsing fallback
     */
    localCvParsing(text) {
        const lines = text.split('\n').filter(l => l.trim());

        // Simple extraction
        const emailMatch = text.match(/[\w.-]+@[\w.-]+\.\w+/);
        const phoneMatch = text.match(/\+?[\d\s-]{10,}/);

        return {
            name: lines[0] || 'Unknown',
            email: emailMatch ? emailMatch[0] : '',
            phone: phoneMatch ? phoneMatch[0] : '',
            location: '',
            summary: '',
            experience: [],
            education: [],
            skills: [],
            certifications: [],
            languages: []
        };
    }

    /**
     * Improve entire CV
     */
    async improveEntireCv(cvData) {
        Logger.info('Improving entire CV', { cvId: cvData.id });

        const results = {
            summary: null,
            experience: [],
            skills: [],
            atsScore: null
        };

        // Improve summary if exists
        if (cvData.summary) {
            results.summary = await this.improveText(cvData.summary, 'summary');
        }

        // Improve experience bullets
        if (cvData.experience) {
            for (const exp of cvData.experience) {
                if (exp.description) {
                    const improved = await this.improveText(exp.description, 'bullet');
                    results.experience.push({
                        ...exp,
                        improved: improved.improved,
                        score: improved.score
                    });
                }
            }
        }

        // Suggest additional skills based on experience
        const allExpText = cvData.experience?.map(e => e.description).join(' ') || '';
        const suggestedSkills = await this.suggestSkills(allExpText);
        results.skills = {
            current: cvData.skills || [],
            suggested: suggestedSkills
        };

        // Get ATS score
        results.atsScore = await this.calculateAtsScore(cvData);

        return results;
    }

    /**
     * Suggest skills based on experience text
     */
    async suggestSkills(experienceText) {
        if (!experienceText) return [];

        const prompt = `Based on this work experience description, suggest relevant technical and soft skills that should be added to a CV.

Experience:
${experienceText}

Provide skills as a JSON array: ["skill1", "skill2", ...]`;

        try {
            const response = await aiRouter.chat({
                messages: [{ role: 'user', content: prompt }],
                provider: this.defaultProvider
            });

            return JSON.parse(response.content);
        } catch {
            return [];
        }
    }
}

// Export singleton
const cvEnhancer = new CVEnhancer({
    defaultProvider: process.env.CV_AI_PROVIDER || 'auto'
});

export { CVEnhancer, cvEnhancer };
export default cvEnhancer;