export type User = {
  id: number;
  name: string;
  email: string;
  role: App.Enums.Security.UserRole;
  customer_id?: number | null;
  avatar?: string;
  email_verified_at: string | null;
  created_at: string;
  updated_at: string;
  [key: string]: unknown;
};

export type Auth = {
  user: User;
};
